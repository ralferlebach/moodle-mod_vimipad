# mod_vimipad — load tests (JMeter)

`vimipad-read-endpoints.jmx` drives the read-heavy web-service functions —
`get_workspace`, `get_operations`, `get_layout_history`, `get_revision_state` —
against a seeded **large** map, to check server response times and catch N+1
regressions. It runs against a live Moodle over the REST web service; it is not
part of the static `moodle-plugin-ci` jobs.

## 1. Seed a large map

Use the plugin generator's large profile (1000 nodes / 2000 relations /
200 containers) plus a long operation history. In a disposable site's PHPUnit
bootstrap or a small CLI:

```php
$gen = $generator->get_plugin_generator('mod_vimipad');
$ws  = $gen->create_map_profile($instance, $userid, 'large');
$gen->create_collaboration_history($ws, 20000); // heavy op-log for get_operations
```

Note the resulting **workspace id** and the activity **course-module id**.

## 2. Enable web services and mint a token

On the site: enable the REST protocol, add the four `mod_vimipad_get_*` read
functions to an external service, and create a token for an enrolled user with
`mod/vimipad:view`. Copy the token.

## 3. Run

```bash
jmeter -n -t vimipad-read-endpoints.jmx \
  -Jbase_url=http://localhost:8000 \
  -Jtoken=YOUR_TOKEN \
  -Jworkspaceid=WORKSPACE_ID \
  -Jcmid=CMID \
  -Jrevision=2000 \
  -Jthreads=25 -Jrampup=10 -Jloops=20 \
  -Jmaxduration=2000 \
  -l vimipad-load-results.jtl
```

All parameters have defaults (see the plan's User Defined Variables). The run
fails a sample if the response contains `"exception"` or exceeds `maxduration`
(default 2000 ms — a server-side budget, looser than the ~200 ms client-side
interaction target).

## 4. Read the results

`vimipad-load-results.jtl` feeds JMeter's Summary/Aggregate report. Watch the
95th-percentile latency of `get_operations` and `get_revision_state` as the
op-log grows: a jump that scales with history size points at an N+1 or a missing
index rather than raw data volume.
