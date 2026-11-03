# Router protocol (MikroTik, PUSH model)

Single reference for the wire format between the RouterOS scripts
(`backend/resources/routeros/*.rsc`), the installer and the Laravel API.
RouterOS 7.12 has no JSON serializer, so routers speak plain text. The scripts
are the source of truth; `RouterProtocolContractTest` reads the real files and
fails when a script, a route or this table drift apart.

## Routes and headers

| Purpose | Method and path | Auth |
|---|---|---|
| Reachability check (`install-precheck.rsc`) | `GET /api/v1/hotspot/ping` | public, `throttle:60,1`; body `ok` |
| Installer download | `GET /api/v1/hotspot/install/{token}.rsc` | one-time token in the path |
| Sync | `POST /api/v1/hotspot/{code}/sync` | `X-API-Key`, `throttle:router` (12/min per router) |
| Script download (installer and `update-script`) | `GET /api/v1/hotspot/{code}/scripts/{name}` | `X-API-Key`, `throttle:router` |

`{code}` is the workspace code. The only router header is `X-API-Key`.
`{name}` is one of the files in `version.json` (`hooks`, `hooks.rsc` or the
system script name `cws-hooks`); anything else is `404`. The script is rendered
for the calling router (no `{{PLACEHOLDER}}` left) and carries no API key: the
router keeps its key in memory and in `cws-state.txt`. Its sha256 is
`RouterScriptRepository::renderedSha()`, which `update-script` commands carry.

## Sync request (`Content-Type: text/plain`)

One record per line, fields separated by `|`. Order of sections: `H`, `E*`, `S` plus rows, `A*`.

```
H|batch_id|seq|boot_id|now|mode|nonce|script_version|serial|routeros_version
E|seq|type|date time|user|mac|ip|uptime|bytes-in|bytes-out
S|snapshot_id|index|total
user|mac|ip|uptime|bytes-in|bytes-out
A|command_id|status
```

- `H` is mandatory and appears once. `serial` and `routeros_version` are the last two fields and may be absent or empty. `mode` is `full` or `poll-only`. `nonce` is `[A-Za-z0-9]{1,64}`.
- Times are the router's `date time` text (`2026-11-03 12:30:00` or `nov/03/2026 12:30:00`). The server reads `now` and every event time with the same rule and shifts the events by `server_now - router_now`, so a wrong router clock is corrected.
- `E`: zero or more. Empty fields become null; `uptime`, `bytes-in`, `bytes-out` are stored in the event `data`. Event identity is `boot_id-seq`.
- `S`: at most one chunk. Rows follow it until a line that does not have exactly 6 fields (a user name may spell `A` or `E`, so the field count tells a row from a section line). An `S` line without rows is an empty chunk: nobody is online.
- `A`: `status` is `ok` or the rejection reason from `interpreter.rsc` (`bad-args`, `not-allowed`, `poll-only`, `sha-mismatch`, `failed`).
- A malformed line answers `422` and stores nothing.

JSON is still accepted as an internal array form (the same keys, `at` and `now` as integers, `acks` as `{command_id, status}` objects); routers never send it.

## Sync reply (`text/plain`, at most 4 KB)

```
CWS-SYNC <nonce>
C|command_id|verb|arg1|arg2|arg3
```

The first line echoes the request nonce; `interpreter.rsc` drops the whole reply otherwise. Resending a `batch_id` returns the identical cached reply and stores nothing new (10 minutes). `C|` lines are produced by the command queue (task 2.5).

## Limits

500 events, 200 snapshot chunks, 60000 bytes per chunk, 100 acks per batch.
