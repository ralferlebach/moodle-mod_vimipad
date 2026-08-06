// mod_vimipad — load test (k6), read-heavy web-service functions.
//
// Pendant zum JMeter-Plan `vimipad-read-endpoints.jmx`: treibt dieselben vier
// Read-Funktionen (get_workspace, get_operations, get_layout_history,
// get_revision_state) gegen eine geseedete große Map über Moodles REST-Web-
// Service. Läuft gegen eine LIVE-Moodle-Site, ist NICHT Teil der
// moodle-plugin-ci-Pipeline. Siehe README.md für Seed & Token.
//
// Beispiel:
//   k6 run \
//     -e BASE_URL=http://localhost:8000 \
//     -e TOKEN=YOUR_TOKEN \
//     -e WORKSPACEID=123 \
//     -e CMID=45 \
//     -e REVISION=2000 \
//     -e VUS=25 -e DURATION=60s -e MAXMS=2000 \
//     tests/load/vimipad-read-endpoints.k6.js

import http from 'k6/http';
import { check } from 'k6';
import { Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const TOKEN = __ENV.TOKEN || '';
const WORKSPACEID = __ENV.WORKSPACEID || '1';
const CMID = __ENV.CMID || '1';
const REVISION = __ENV.REVISION || '0';
const MAXMS = Number(__ENV.MAXMS || '2000'); // server-side budget, looser than the ~200ms client target

// Per-endpoint latency so a regression can be pinned to one function.
const t = {
  get_workspace: new Trend('vimipad_get_workspace', true),
  get_operations: new Trend('vimipad_get_operations', true),
  get_layout_history: new Trend('vimipad_get_layout_history', true),
  get_revision_state: new Trend('vimipad_get_revision_state', true),
};

export const options = {
  vus: Number(__ENV.VUS || '25'),
  duration: __ENV.DURATION || '60s',
  thresholds: {
    // 95th percentile under the budget; <1% failed checks.
    'http_req_duration': [`p(95)<${MAXMS}`],
    'checks': ['rate>0.99'],
  },
};

function call(fn, params) {
  const url = `${BASE}/webservice/rest/server.php`;
  const body = Object.assign(
    {
      wstoken: TOKEN,
      wsfunction: `mod_vimipad_${fn}`,
      moodlewsrestformat: 'json',
    },
    params
  );
  const res = http.post(url, body);
  t[fn].add(res.timings.duration);
  check(res, {
    [`${fn} status 200`]: (r) => r.status === 200,
    [`${fn} no exception`]: (r) => r.body && r.body.indexOf('"exception"') === -1,
    [`${fn} under budget`]: (r) => r.timings.duration < MAXMS,
  });
  return res;
}

export default function () {
  call('get_workspace', { cmid: CMID });
  call('get_operations', { cmid: CMID, workspaceid: WORKSPACEID, torevision: REVISION });
  call('get_layout_history', { cmid: CMID, workspaceid: WORKSPACEID });
  call('get_revision_state', { cmid: CMID, workspaceid: WORKSPACEID, revision: REVISION });
}
