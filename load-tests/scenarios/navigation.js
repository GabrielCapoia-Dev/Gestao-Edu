import exec from 'k6/execution';
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }));

const BASE_URL = normalizeBaseUrl(__ENV.K6_BASE_URL || 'https://edu.hubdetestes.online');
const USERS_FILE = __ENV.K6_USERS_FILE || '/scripts/data/users.local.csv';
const PROFILE = __ENV.K6_PROFILE || 'smoke';
const INCLUDE_HEARTBEAT = String(__ENV.K6_INCLUDE_HEARTBEAT || 'false').toLowerCase() === 'true';
const PAGE_DELAY_MIN = Number(__ENV.K6_PAGE_DELAY_MIN || 1);
const PAGE_DELAY_MAX = Number(__ENV.K6_PAGE_DELAY_MAX || 4);
const GLOBAL_PAGES = parsePages(__ENV.K6_NAV_PATHS);
const PROFILE_PAGES = parseProfilePages(__ENV.K6_PROFILE_PATHS) || defaultProfilePages();

const loginSuccessRate = new Rate('gestao_login_success');
const pageSuccessRate = new Rate('gestao_page_success');
const forbiddenRate = new Rate('gestao_page_forbidden');
const loginDuration = new Trend('gestao_login_duration', true);

const users = parseUsers(open(USERS_FILE));

let sessionUser = null;
let sessionReady = false;

export const options = profileOptions(PROFILE);

export default function () {
  if (!sessionReady) {
    sessionUser = pickUserForVu();

    group('login', () => {
      const login = loginAs(sessionUser);

      if (!login.ok) {
        loginSuccessRate.add(false, { profile: sessionUser.profile });
        return;
      }

      sessionReady = true;
      loginSuccessRate.add(true, { profile: sessionUser.profile });
    });
  }

  if (!sessionReady || !sessionUser) {
    return;
  }

  group('navigation', () => {
    const sequence = rotatePages(pagesFor(sessionUser));

    for (const page of sequence) {
      visitPage(page, sessionUser);
      sleep(randomBetween(PAGE_DELAY_MIN, PAGE_DELAY_MAX));
    }

    if (INCLUDE_HEARTBEAT) {
      postHeartbeat();
    }
  });
}

function loginAs(user) {
  const started = Date.now();
  const loginPage = http.get(`${BASE_URL}/admin/login`, {
    tags: { kind: 'login', page: 'login_form' },
    responseType: 'text',
  });

  const token = csrfToken(loginPage.body);

  const formOk = check(loginPage, {
    'login page loaded': (response) => response.status === 200,
    'csrf token found': () => Boolean(token),
  });

  if (!formOk || !token) {
    loginDuration.add(Date.now() - started);
    return { ok: false };
  }

  const loginResponse = http.post(
    `${BASE_URL}/admin/login`,
    {
      _token: token,
      email: user.email,
      password: user.password,
      remember: '1',
    },
    {
      redirects: 5,
      tags: { kind: 'login', page: 'login_submit', profile: user.profile },
      responseType: 'text',
    },
  );

  const ok = check(loginResponse, {
    'login accepted': (response) => response.status >= 200 && response.status < 400,
    'not returned to login': (response) => !isLoginPage(response),
    'not forced password change': (response) => !String(response.url || '').includes('alterar-senha-obrigatoria'),
  });

  loginDuration.add(Date.now() - started);

  return { ok };
}

function visitPage(page, user) {
  const response = http.get(`${BASE_URL}${page.path}`, {
    redirects: 5,
    tags: { kind: 'page', page: page.name, profile: user.profile },
    responseType: 'none',
  });

  const forbidden = response.status === 403;
  const ok = response.status >= 200 && response.status < 400;

  forbiddenRate.add(forbidden, { page: page.name, profile: user.profile });
  pageSuccessRate.add(ok, { page: page.name, profile: user.profile });

  check(response, {
    [`${page.name} loaded`]: () => ok,
    [`${page.name} not forbidden`]: () => !forbidden,
  });
}

function postHeartbeat() {
  http.post(`${BASE_URL}/admin/presence/heartbeat`, null, {
    redirects: 2,
    tags: { kind: 'heartbeat', page: 'presence' },
    responseType: 'none',
  });
}

function pickUserForVu() {
  const index = (exec.vu.idInTest - 1) % users.length;

  return users[index];
}

function rotatePages(source) {
  const offset = (exec.vu.idInTest + exec.scenario.iterationInTest) % source.length;

  return source.slice(offset).concat(source.slice(0, offset));
}

function csrfToken(html) {
  const tokenMatch = String(html || '').match(/name=["']_token["'][^>]*value=["']([^"']+)["']/i);

  return tokenMatch ? tokenMatch[1] : null;
}

function isLoginPage(response) {
  return String(response.url || '').includes('/admin/login')
    || String(response.body || '').includes('Acessar o sistema');
}

function parseUsers(csv) {
  const rows = String(csv || '')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'));

  if (rows.length < 2) {
    throw new Error(`Arquivo de usuarios vazio ou invalido: ${USERS_FILE}`);
  }

  return rows.slice(1).map((line, index) => {
    const [email, password, profile = 'default'] = line.split(',').map((value) => value.trim());

    if (!email || !password) {
      throw new Error(`Linha ${index + 2} invalida em ${USERS_FILE}`);
    }

    return { email, password, profile };
  });
}

function parsePages(value) {
  if (!value) {
    return null;
  }

  const parsed = value
    .split(';')
    .map((item) => item.trim())
    .filter(Boolean)
    .map((item) => {
      const [name, path] = item.split(':');

      if (!name || !path || !path.startsWith('/')) {
        throw new Error(`K6_NAV_PATHS invalido no item: ${item}`);
      }

      return { name, path };
    });

  return parsed.length > 0 ? parsed : null;
}

function parseProfilePages(value) {
  if (!value) {
    return null;
  }

  const result = {};

  for (const group of value.split(';').map((item) => item.trim()).filter(Boolean)) {
    const separator = group.indexOf('=');

    if (separator <= 0) {
      throw new Error(`K6_PROFILE_PATHS invalido no grupo: ${group}`);
    }

    const profile = group.slice(0, separator).trim();
    const pageList = group.slice(separator + 1).trim();
    const pages = parsePages(pageList.split(',').join(';'));

    if (!profile || !pages || pages.length === 0) {
      throw new Error(`K6_PROFILE_PATHS invalido para o perfil: ${profile}`);
    }

    result[profile] = pages;
  }

  return Object.keys(result).length > 0 ? result : null;
}

function defaultProfilePages() {
  const common = [
    { name: 'dashboard', path: '/admin/dashboard' },
    { name: 'profile', path: '/admin/profile' },
  ];

  return {
    default: common,
    staff: common.concat([
      { name: 'usuarios', path: '/admin/usuarios' },
      { name: 'pedidos', path: '/admin/pedidos' },
      { name: 'estoque', path: '/admin/gestao-estoque' },
      { name: 'inventario', path: '/admin/gestao-inventario' },
      { name: 'relatorios', path: '/admin/relatorios-dashboard' },
    ]),
    school: common.concat([
      { name: 'alunos', path: '/admin/alunos' },
      { name: 'turmas', path: '/admin/turmas' },
      { name: 'inventario', path: '/admin/gestao-inventario' },
    ]),
    cmei: common.concat([
      { name: 'alunos', path: '/admin/alunos' },
      { name: 'turmas', path: '/admin/turmas' },
      { name: 'inventario', path: '/admin/gestao-inventario' },
    ]),
  };
}

function pagesFor(user) {
  if (GLOBAL_PAGES) {
    return GLOBAL_PAGES;
  }

  return PROFILE_PAGES[user.profile] || PROFILE_PAGES.default;
}

function profileOptions(profile) {
  const commonThresholds = {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{kind:page}': ['p(95)<2000'],
    gestao_page_forbidden: ['rate<0.01'],
    gestao_login_success: ['rate>0.95'],
    gestao_page_success: ['rate>0.95'],
  };

  if (profile === 'conservative') {
    return {
      stages: [
        { duration: '1m', target: 5 },
        { duration: '3m', target: 20 },
        { duration: '1m', target: 0 },
      ],
      thresholds: commonThresholds,
      userAgent: 'GestaoEduK6/1.0',
    };
  }

  if (profile === 'medium') {
    return {
      stages: [
        { duration: '2m', target: 20 },
        { duration: '6m', target: 50 },
        { duration: '2m', target: 0 },
      ],
      thresholds: commonThresholds,
      userAgent: 'GestaoEduK6/1.0',
    };
  }

  if (profile === 'large') {
    return {
      stages: [
        { duration: '3m', target: 50 },
        { duration: '8m', target: 100 },
        { duration: '3m', target: 0 },
      ],
      thresholds: commonThresholds,
      userAgent: 'GestaoEduK6/1.0',
    };
  }

  if (profile === 'xlarge') {
    return {
      stages: [
        { duration: '3m', target: 50 },
        { duration: '5m', target: 100 },
        { duration: '8m', target: 150 },
        { duration: '3m', target: 0 },
      ],
      thresholds: commonThresholds,
      userAgent: 'GestaoEduK6/1.0',
    };
  }

  if (profile === 'target300') {
    return {
      stages: [
        { duration: '4m', target: 50 },
        { duration: '5m', target: 100 },
        { duration: '5m', target: 150 },
        { duration: '10m', target: 300 },
        { duration: '5m', target: 0 },
      ],
      thresholds: commonThresholds,
      userAgent: 'GestaoEduK6/1.0',
    };
  }

  return {
    vus: 3,
    duration: '1m',
    thresholds: commonThresholds,
    userAgent: 'GestaoEduK6/1.0',
  };
}

function normalizeBaseUrl(value) {
  return String(value).replace(/\/+$/, '');
}

function randomBetween(min, max) {
  const lower = Number.isFinite(min) ? min : 1;
  const upper = Number.isFinite(max) && max >= lower ? max : lower;

  return lower + Math.random() * (upper - lower);
}
