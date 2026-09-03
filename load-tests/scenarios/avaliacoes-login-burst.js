import exec from 'k6/execution';
import http from 'k6/http';
import { check } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = String(__ENV.K6_BASE_URL || 'https://edu.hubdetestes.online').replace(/\/+$/, '');
const USERS_FILE = __ENV.K6_AVALIACAO_USERS_FILE || '../data/avaliacoes-users.local.csv';
const VUS = Number(__ENV.K6_LOGIN_BURST_VUS || 20);
const users = parseUsers(open(USERS_FILE));
const loginDuration = new Trend('login_burst_duration', true);

if (users.length < VUS) throw new Error(`O CSV possui ${users.length} usuarios; a rajada exige ${VUS}.`);

export const options = {
  scenarios: {
    login_burst: {
      executor: 'per-vu-iterations',
      vus: VUS,
      iterations: 1,
      maxDuration: '30s',
    },
  },
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate==0'],
    login_burst_duration: ['p(95)<2500', 'p(99)<4000'],
  },
};

export default function () {
  const user = users[(exec.vu.idInTest - 1) % users.length];
  const started = Date.now();
  const loginPage = http.get(`${BASE_URL}/admin/login`, { responseType: 'text', tags: { operation: 'login_form' } });
  const token = String(loginPage.body || '').match(/name=["']_token["'][^>]*value=["']([^"']+)/i)?.[1] || null;
  const login = http.post(`${BASE_URL}/admin/login`, {
    _token: token,
    email: user.email,
    password: user.password,
    remember: '1',
  }, { redirects: 0, responseType: 'text', tags: { operation: 'login_submit' } });
  const cookies = mergeResponseCookies('', login);
  const dashboard = http.get(`${BASE_URL}/admin`, {
    redirects: 2,
    responseType: 'text',
    headers: { Cookie: cookies },
    tags: { operation: 'authenticated_dashboard' },
  });
  loginDuration.add(Date.now() - started);

  check(loginPage, { 'login form loaded': (item) => item.status === 200 && Boolean(token) });
  check(login, { 'login redirects': (item) => item.status >= 300 && item.status < 400 });
  check(dashboard, {
    'authenticated dashboard loaded': (item) => item.status === 200,
    'session remains authenticated': (item) => !String(item.url || '').includes('/admin/login'),
  });
}

function parseUsers(csv) {
  return String(csv || '').split(/\r?\n/).map((line) => line.trim()).filter(Boolean).slice(1).map((line) => {
    const [email, password] = line.split(',').map((value) => value.trim());
    return { email, password };
  });
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
