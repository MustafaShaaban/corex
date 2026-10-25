# Contract: the upload routes' sign-in rule, and the maintenance gate's body source

## 1. Upload routes (R4, R5, DECISIONS #502)

These three routes are flagged in `ReleasesController::route()`:

| Route | Method | Change |
|---|---|---|
| `corex/v1/releases/uploads/<sha256>` | GET | sign-in rule below; the answer gains `part_bytes` |
| `corex/v1/releases/uploads/<sha256>` | POST | sign-in rule below |
| `corex/v1/releases/uploads/<sha256>/complete` | POST | sign-in rule below; writes `agent.release.uploaded` when signed with an Application Password |

`ReleaseRestGateway::answer()` lets a flagged route's request through when the nonce is valid, as today, or when
**all** of the following hold:

1. `rest_get_authenticated_app_password()` is not null (WordPress signed the request in with an Application
   Password);
2. `features.agent_changes` is on;
3. `mayManage()` is true. The route's permission callback has already checked this, and it is checked again here
   for the same reason the nonce is.

Otherwise the request gets `403 not_signed`, as today. When the cause is the switch, the answer is
`403 agent_changes_off`.

Every other release route is unchanged: nonce only.

`part_bytes` is computed as `ReleaseDesk` already does for the screen:
`max(256 KiB, min(4 MiB, wp_max_upload_size() / 2))`.

## 2. The gate's fourth source (R3, DECISIONS #506)

`ReleaseMaintenance::GATE` is the PHP written to `.maintenance` while folders are swapped. After the header,
server-header and address sources, it reads the body only when **all** of the following hold:
- `$_SERVER['REQUEST_METHOD']` is `POST`;
- `$_SERVER['CONTENT_TYPE']` contains `json`;
- `(int) $_SERVER['CONTENT_LENGTH']` is greater than 0 and at most 65,536.

When they hold, it:
1. reads `file_get_contents( 'php://input' )`;
2. runs `json_decode( ..., true, 16 )`;
3. walks at most 64 nodes, breadth first, for the first string value under the key `corex_release_key`;
4. checks that value as it checks the others: `hash_equals( keyHash, hash( 'sha256', $value ) )`.

On a match, `COREX_RELEASE_KEY_FROM` is `body`. `ReleaseMaintenance::keyCameFrom()` and the install event's
`key_from` gain that value.

What stays true:
- The gate decides nothing else.
- A body that is too large, not JSON or malformed is skipped, and the request meets the maintenance answer as
  today.
- `php://input` can still be read by WordPress afterwards.
- Spec 107 FR-024a, amended in this branch, lists the fourth source.

Tested in `tests/Unit/Releases/ReleaseMaintenanceTest.php`, which includes the gate the way
`wp_is_maintenance_mode()` does. The cases are:
- a matching key in an MCP-shaped body;
- a wrong key;
- a body over the limit;
- a non-JSON body;
- a `GET`;
- a key nested past the walk limit.
