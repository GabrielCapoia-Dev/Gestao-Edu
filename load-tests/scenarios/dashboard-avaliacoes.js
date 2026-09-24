import exec from 'k6/execution';
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = String(__ENV.K6_BASE_URL || 'https://edu.hubdetestes.online').replace(/\/+$/, '');
const USERS_FILE = __ENV.K6_DASHBOARD_USERS_FILE || '../data/dashboard-users.csv';
const VUS = Number(__ENV.K6_DASHBOARD_VUS || 20);
const RAMP_UP = __ENV.K6_DASHBOARD_RAMP_UP || '30s';
const HOLD = __ENV.K6_DASHBOARD_HOLD || '30s';
const RAMP_DOWN = __ENV.K6_DASHBOARD_RAMP_DOWN || '5s';
const ONE_SHOT_PAUSE = Number(__ENV.K6_DASHBOARD_ONE_SHOT_PAUSE || 90);
const ONE_SHOT = String(__ENV.K6_DASHBOARD_ONE_SHOT || '').toLowerCase() === 'true';
const EVALUATION_ID = Number(__ENV.K6_DASHBOARD_EVALUATION_ID || 3);
const users = parseUsers(open(USERS_FILE));

const loginDuration = new Trend('dashboard_login_duration', true);
const pageDuration = new Trend('dashboard_page_duration', true);
const actionDuration = new Trend('dashboard_initial_action_duration', true);
const actionSuccess = new Rate('dashboard_initial_action_success');
let authenticated = false;
let sessionCookie = '';

if (users.length < VUS) {
  throw new Error(`O CSV possui ${users.length} usuarios; o teste exige ao menos ${VUS}.`);
}

export const options = {
  scenarios: {
    sustained: {
      ...(ONE_SHOT
        ? { executor: 'per-vu-iterations', vus: VUS, iterations: 1, maxDuration: '3m' }
        : {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
              { duration: RAMP_UP, target: VUS },
              { duration: HOLD, target: VUS },
              { duration: RAMP_DOWN, target: 0 },
            ],
            gracefulRampDown: '100s',
            gracefulStop: '100s',
          }),
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    http_req_failed: ['rate<0.01'],
    dashboard_initial_action_success: ['rate>0.99'],
  },
};

export function setup() {
  if (!ONE_SHOT) return null;

  // Autentica antes da janela concorrente para medir a tela, sem misturar
  // contenção do endpoint de login com a consulta do acompanhamento.
  return users.slice(0, VUS).map((user) => loginAs(user));
}

export default function (sessions) {
  const userIndex = (exec.vu.idInTest - 1) % users.length;
  const user = users[userIndex];
  if (ONE_SHOT) {
    sessionCookie = sessions?.[userIndex] || '';
  } else if (!authenticated) {
    sessionCookie = loginAs(user);
    if (!sessionCookie) return;
    authenticated = true;
  }

  const pageStarted = Date.now();
  const page = http.get(`${BASE_URL}/admin/dashboard-avaliacoes?avaliacao=${EVALUATION_ID}`, {
    responseType: 'text',
    headers: { Cookie: sessionCookie },
    tags: { operation: 'dashboard_page' },
  });
  sessionCookie = mergeResponseCookies(sessionCookie, page);
  pageDuration.add(Date.now() - pageStarted);

  const csrf = String(page.body || '').match(/name=["']csrf-token["'][^>]*content=["']([^"']+)/i)?.[1] || null;
  const snapshot = livewireSnapshot(page.body);
  const updateUri = String(page.body || '').match(/data-update-uri=["']([^"']+)/i)?.[1] || '/livewire/update';
  const updateUrl = /^https?:\/\//i.test(updateUri) ? updateUri : `${BASE_URL}${updateUri}`;
  const pageOk = check(page, {
    'dashboard page is available': (response) => response.status === 200,
    'dashboard user is authenticated': (response) => !String(response.url || '').includes('/admin/login'),
    'dashboard livewire state and csrf exist': () => Boolean(snapshot && csrf),
  });
  if (!pageOk) {
    console.log(JSON.stringify({
      pageUrl: page.url,
      status: page.status,
      updateUri,
      csrfPresent: Boolean(csrf),
      snapshotPresent: Boolean(snapshot),
      snapshotNames: snapshotNames(page.body),
    }));
    return;
  }

  const started = Date.now();
  const action = http.post(updateUrl, JSON.stringify({
    components: [{
      snapshot,
      updates: {},
      calls: [{ method: 'carregarDashboardInicialCompleto', params: [] }],
    }],
  }), {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrf,
      'X-Livewire': 'true',
      'Referer': `${BASE_URL}/admin/dashboard-avaliacoes?avaliacao=${EVALUATION_ID}`,
      Cookie: sessionCookie,
    },
    tags: { operation: 'dashboard_initial_action' },
  });
  actionDuration.add(Date.now() - started);

  const success = action.status === 200 && !String(action.body || '').includes('"exception"');
  actionSuccess.add(success);
  sessionCookie = mergeResponseCookies(sessionCookie, action);
  check(action, {
    'initial indicators action completed': () => success,
  });
  // No teste de fluxo sustentado, representa o usuário lendo sem refresh.
  // O modo one-shot encerra a iteração logo após a ação medida.
  if (!ONE_SHOT) sleep(ONE_SHOT_PAUSE);
}

function loginAs(user) {
  const loginStarted = Date.now();
  const loginPage = http.get(`${BASE_URL}/admin/login`, { responseType: 'text' });
  const token = String(loginPage.body || '').match(/name=["']_token["'][^>]*value=["']([^"']+)/i)?.[1] || null;
  const login = http.post(`${BASE_URL}/admin/login`, {
    _token: token,
    email: user.email,
    password: user.password,
    remember: '1',
  }, { redirects: 0, responseType: 'text' });
  loginDuration.add(Date.now() - loginStarted);

  const ok = check(login, {
    'login form and csrf loaded': () => loginPage.status === 200 && Boolean(token),
    'login accepted': (response) => response.status >= 300 && response.status < 400,
  });

  return ok ? mergeResponseCookies('', login) : '';
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

function parseUsers(csv) {
  return String(csv || '').split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'))
    .slice(1)
    .map((line) => {
      const [email, password] = line.split(',').map((value) => value.trim());
      if (!email || !password) throw new Error('Linha invalida no CSV de usuarios do dashboard.');
      return { email, password };
    });
}

function livewireSnapshot(html) {
  for (const match of String(html || '').matchAll(/wire:snapshot="([\s\S]*?)"/g)) {
    const snapshot = decodeHtml(match[1]);
    try {
      const parsed = JSON.parse(snapshot);
      if (String(parsed?.memo?.name || '').toLowerCase().includes('dashboardavaliacoes')) return snapshot;
    } catch (_) {}
  }
  return null;
}

function snapshotNames(html) {
  const names = [];
  for (const match of String(html || '').matchAll(/wire:snapshot="([\s\S]*?)"/g)) {
    try {
      names.push(JSON.parse(decodeHtml(match[1]))?.memo?.name || 'unknown');
    } catch (_) {
      names.push('invalid-json');
    }
  }
  return names;
}

function decodeHtml(value) {
  return String(value)
    .replaceAll('&quot;', '"')
    .replaceAll('&#039;', "'")
    .replaceAll('&lt;', '<')
    .replaceAll('&gt;', '>')
    .replaceAll('&amp;', '&');
}
