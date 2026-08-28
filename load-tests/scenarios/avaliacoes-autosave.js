import exec from 'k6/execution';
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = String(__ENV.K6_BASE_URL || 'https://edu.hubdetestes.online').replace(/\/+$/, '');
const USERS_FILE = __ENV.K6_AVALIACAO_USERS_FILE || '/scripts/data/avaliacoes-users.local.csv';
const VUS = Number(__ENV.K6_AVALIACAO_VUS || 10);
const DURATION = __ENV.K6_AVALIACAO_DURATION || '5m';
const P95_GATE = Number(__ENV.K6_AVALIACAO_P95_MS || 1200);
const users = parseUsers(open(USERS_FILE));

const autosaveDuration = new Trend('avaliacao_autosave_duration', true);
const autosaveSuccess = new Rate('avaliacao_autosave_success');
const conflictRate = new Rate('avaliacao_autosave_conflict');

export const options = {
  vus: VUS,
  duration: DURATION,
  thresholds: {
    avaliacao_autosave_success: ['rate>0.99'],
    avaliacao_autosave_conflict: ['rate<0.01'],
    avaliacao_autosave_duration: [`p(95)<${P95_GATE}`],
    http_req_failed: ['rate<0.01'],
  },
};

let state = null;

export default function () {
  if (!state) {
    state = bootstrap(users[(exec.vu.idInTest - 1) % users.length]);
  }
  if (!state) {
    return;
  }

  const alunoId = state.user.alunoIds[exec.scenario.iterationInTest % state.user.alunoIds.length];
  const property = `respostas.${state.user.pautaId}.${alunoId}.alternativa_id`;
  const started = Date.now();
  const response = http.post(`${BASE_URL}/livewire/update`, JSON.stringify({
    _token: state.csrf,
    components: [{ snapshot: state.snapshot, updates: { [property]: state.user.alternativaId }, calls: [] }],
  }), {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': state.csrf,
      'X-Livewire': 'true',
    },
    tags: {
      kind: 'avaliacao_autosave',
      professor: state.user.label,
      pauta_id: String(state.user.pautaId),
      turma_id: String(state.user.turmaId),
    },
  });
  autosaveDuration.add(Date.now() - started);

  const body = safeJson(response.body);
  const conflict = response.status === 409 || String(response.body || '').includes('alterada por outro usuário');
  const ok = response.status === 200 && body?.components?.[0]?.snapshot;
  conflictRate.add(conflict);
  autosaveSuccess.add(Boolean(ok));
  check(response, {
    'autosave accepted': () => Boolean(ok),
    'no silent server error': (item) => item.status < 500,
  });

  if (ok) {
    state.snapshot = body.components[0].snapshot;
  }
  sleep(Number(__ENV.K6_AVALIACAO_INTERVAL_SECONDS || 0.7));
}

function bootstrap(user) {
  const loginPage = http.get(`${BASE_URL}/admin/login`, { responseType: 'text' });
  const loginToken = csrfFromForm(loginPage.body);
  const login = http.post(`${BASE_URL}/admin/login`, {
    _token: loginToken,
    email: user.email,
    password: user.password,
    remember: '1',
  }, { redirects: 5, responseType: 'text' });

  if (login.status >= 400 || String(login.url || '').includes('/admin/login')) {
    autosaveSuccess.add(false);
    return null;
  }

  const path = `/admin/avaliacoes-professor?avaliacao=${user.avaliacaoId}&turma=${user.turmaId}`;
  const workspace = http.get(`${BASE_URL}${path}`, { responseType: 'text' });
  const csrf = csrfFromMeta(workspace.body);
  const snapshot = livewireSnapshot(workspace.body);

  const ok = check(workspace, {
    'workspace loaded': (item) => item.status === 200,
    'livewire snapshot found': () => Boolean(snapshot),
    'csrf found': () => Boolean(csrf),
  });

  return ok && snapshot && csrf ? { user, csrf, snapshot } : null;
}

function parseUsers(csv) {
  const rows = String(csv || '').split(/\r?\n/).map((line) => line.trim()).filter((line) => line && !line.startsWith('#'));
  if (rows.length < 2) throw new Error('CSV de professores de avaliação vazio.');

  return rows.slice(1).map((line, index) => {
    const [email, password, avaliacaoId, turmaId, pautaId, alternativaId, alunoIds] = line.split(',').map((value) => value.trim());
    if (![email, password, avaliacaoId, turmaId, pautaId, alternativaId, alunoIds].every(Boolean)) {
      throw new Error(`Linha ${index + 2} inválida no CSV de avaliações.`);
    }
    return {
      email,
      password,
      avaliacaoId: Number(avaliacaoId),
      turmaId: Number(turmaId),
      pautaId: Number(pautaId),
      alternativaId: Number(alternativaId),
      alunoIds: alunoIds.split('|').map(Number).filter(Boolean),
      label: email.split('@')[0],
    };
  });
}

function livewireSnapshot(html) {
  const matches = String(html || '').matchAll(/wire:snapshot="([\s\S]*?)"/g);
  for (const match of matches) {
    const decoded = decodeHtml(match[1]);
    if (decoded.includes('avaliacao-turma-workspace')) return decoded;
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
