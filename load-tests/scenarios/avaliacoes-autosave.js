import exec from 'k6/execution';
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = String(__ENV.K6_BASE_URL || 'https://edu.hubdetestes.online').replace(/\/+$/, '');
const USERS_FILE = __ENV.K6_AVALIACAO_USERS_FILE || '../data/avaliacoes-users.local.csv';
const VUS = Number(__ENV.K6_AVALIACAO_VUS || 20);
const RAMP_UP = __ENV.K6_AVALIACAO_RAMP_UP || '2m';
const HOLD = __ENV.K6_AVALIACAO_HOLD || '10m';
const RAMP_DOWN = __ENV.K6_AVALIACAO_RAMP_DOWN || '1m';
const INTERVAL_SECONDS = Number(__ENV.K6_AVALIACAO_INTERVAL_SECONDS || 0.7);
const users = parseUsers(open(USERS_FILE));

const loginDuration = new Trend('avaliacao_login_duration', true);
const workspaceDuration = new Trend('avaliacao_workspace_duration', true);
const autosaveDuration = new Trend('avaliacao_autosave_duration', true);
const autosaveSuccess = new Rate('avaliacao_autosave_success');
const conflictRate = new Rate('avaliacao_autosave_conflict');

if (users.length < VUS) {
  throw new Error(`O CSV possui ${users.length} usuarios; o teste exige ao menos ${VUS}.`);
}

export const options = {
  scenarios: {
    sustained: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: RAMP_UP, target: VUS },
        { duration: HOLD, target: VUS },
        { duration: RAMP_DOWN, target: 0 },
      ],
      gracefulRampDown: '15s',
    },
  },
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate==0'],
    avaliacao_autosave_success: ['rate==1'],
    avaliacao_autosave_conflict: ['rate==0'],
    avaliacao_login_duration: ['p(95)<2500', 'p(99)<4000'],
    avaliacao_workspace_duration: ['p(95)<2000', 'p(99)<4000'],
    avaliacao_autosave_duration: ['p(95)<1200', 'p(99)<4000'],
  },
};

let state = null;

export default function () {
  if (!state) {
    state = bootstrap(users[(exec.vu.idInTest - 1) % users.length]);
  }

  if (!state) {
    exec.test.abort('Falha ao autenticar ou carregar o workspace.');
  }

  const student = state.user.students[exec.scenario.iterationInTest % state.user.students.length];
  const alternativeId = state.user.alternativeIds[exec.scenario.iterationInTest % state.user.alternativeIds.length];
  const expectedVersion = state.versions[student.id] ?? student.version;
  const previousAlternativeId = state.values[student.id] ?? null;
  const started = Date.now();
  const response = http.post(`${BASE_URL}/admin/avaliacoes/respostas/autosave`, JSON.stringify({
    _token: state.csrf,
    avaliacao_id: state.user.avaliacaoId,
    turma_id: state.user.turmaId,
    aluno_id: student.id,
    tipo: 'resposta',
    pauta_id: state.user.pautaId,
    campo: 'alternativa_id',
    valor: alternativeId,
    alternativa_id: alternativeId,
    observacao: null,
    expected_version: expectedVersion,
    expected_values: { alternativa_id: previousAlternativeId, observacao: null },
  }), {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': state.csrf,
      'Cookie': state.cookieHeader,
    },
    tags: {
      operation: 'autosave',
      professor: state.user.label,
      pauta_id: String(state.user.pautaId),
      turma_id: String(state.user.turmaId),
    },
  });
  autosaveDuration.add(Date.now() - started);

  const body = safeJson(response.body);
  const conflict = response.status === 409;
  const ok = response.status === 200 && body?.saved === true && Number(body?.version) > expectedVersion;
  conflictRate.add(conflict);
  autosaveSuccess.add(Boolean(ok));
  check(response, {
    'autosave accepted': () => Boolean(ok),
    'autosave version advanced': () => Number(body?.version) > expectedVersion,
    'no server error': (item) => item.status < 500,
  });

  if (ok) {
    state.versions[student.id] = Number(body.version);
    state.values[student.id] = alternativeId;
  }

  state.cookieHeader = mergeResponseCookies(state.cookieHeader, response);
  sleep(INTERVAL_SECONDS);
}

function bootstrap(user) {
  const loginStarted = Date.now();
  const loginPage = http.get(`${BASE_URL}/admin/login`, { responseType: 'text', tags: { operation: 'login_form' } });
  const loginToken = csrfFromForm(loginPage.body);
  const login = http.post(`${BASE_URL}/admin/login`, {
    _token: loginToken,
    email: user.email,
    password: user.password,
    remember: '1',
  }, { redirects: 0, responseType: 'text', tags: { operation: 'login_submit' } });
  loginDuration.add(Date.now() - loginStarted);

  const loginOk = check(login, {
    'login redirects after authentication': (item) => item.status >= 300 && item.status < 400,
    'login session cookie received': (item) => Object.keys(item.cookies || {}).length > 0,
  });

  if (!loginOk) return null;

  const path = `/admin/avaliacoes-professor?avaliacao=${user.avaliacaoId}&turma=${user.turmaId}`;
  const loginCookieHeader = mergeResponseCookies('', login);
  const workspaceStarted = Date.now();
  const workspace = http.get(`${BASE_URL}${path}`, {
    responseType: 'text',
    headers: { Cookie: loginCookieHeader },
    tags: { operation: 'workspace' },
  });
  workspaceDuration.add(Date.now() - workspaceStarted);

  const csrf = csrfFromMeta(workspace.body);
  const snapshot = livewireSnapshot(workspace.body);
  const cookieHeader = mergeResponseCookies(loginCookieHeader, workspace);
  const ok = check(workspace, {
    'workspace loaded': (item) => item.status === 200,
    'workspace is authenticated': (item) => !String(item.url || '').includes('/admin/login'),
    'livewire snapshot found': () => Boolean(snapshot),
    'csrf found': () => Boolean(csrf),
  });

  return ok && snapshot && csrf ? {
    user,
    csrf,
    cookieHeader,
    versions: Object.fromEntries(user.students.map((student) => [student.id, student.version])),
    values: Object.fromEntries(user.students.map((student) => [student.id, null])),
  } : null;
}

function parseUsers(csv) {
  const rows = String(csv || '').split(/\r?\n/).map((line) => line.trim()).filter((line) => line && !line.startsWith('#'));
  if (rows.length < 2) throw new Error('CSV de professores de avaliacao vazio.');

  return rows.slice(1).map((line, index) => {
    const [email, password, avaliacaoId, turmaId, pautaId, alternativeIds, studentVersions] = line.split(',').map((value) => value.trim());
    if (![email, password, avaliacaoId, turmaId, pautaId, alternativeIds, studentVersions].every(Boolean)) {
      throw new Error(`Linha ${index + 2} invalida no CSV de avaliacoes.`);
    }

    return {
      email,
      password,
      avaliacaoId: Number(avaliacaoId),
      turmaId: Number(turmaId),
      pautaId: Number(pautaId),
      alternativeIds: alternativeIds.split('|').map(Number).filter(Boolean),
      students: studentVersions.split('|').map((item) => {
        const [id, version] = item.split(':').map(Number);
        return { id, version };
      }).filter((item) => item.id > 0 && item.version >= 0),
      label: email.split('@')[0],
    };
  });
}

function livewireSnapshot(html) {
  const matches = String(html || '').matchAll(/wire:snapshot="([\s\S]*?)"/g);
  for (const match of matches) {
    const decoded = decodeHtml(match[1]);
    if (decoded.includes('avaliacao-turma-workspace') || decoded.includes('avaliacao-turma-professor-workspace')) return decoded;
  }
  return null;
}

function csrfFromForm(html) {
  return String(html || '').match(/name=["']_token["'][^>]*value=["']([^"']+)/i)?.[1] || null;
}

function csrfFromMeta(html) {
  return String(html || '').match(/name=["']csrf-token["'][^>]*content=["']([^"']+)/i)?.[1] || null;
}

function decodeHtml(value) {
  return String(value).replaceAll('&quot;', '"').replaceAll('&#039;', "'").replaceAll('&lt;', '<').replaceAll('&gt;', '>').replaceAll('&amp;', '&');
}

function safeJson(value) {
  try { return JSON.parse(value); } catch (_) { return null; }
}

function mergeResponseCookies(previous, response) {
  const cookies = {};
  String(previous || '').split(';').forEach((item) => {
    const separator = item.indexOf('=');
    if (separator > 0) cookies[item.slice(0, separator).trim()] = item.slice(separator + 1).trim();
  });

  Object.entries(response.cookies || {}).forEach(([name, values]) => {
    const latest = values?.[values.length - 1];
    if (latest?.value !== undefined) cookies[name] = latest.value;
  });

  return Object.entries(cookies).map(([name, value]) => `${name}=${value}`).join('; ');
}
