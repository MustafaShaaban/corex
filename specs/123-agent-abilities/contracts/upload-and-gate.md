# Contract: the upload routes' sign-in rule, and the maintenance gate's body source

## 1. Every release route (DECISIONS #512, superseding #502)

*Amended 2026-10-25: it was the three upload routes, behind the agent switch.*

`ReleaseRestGateway::answer()` lets a request of **any** Releases route through when its nonce is valid, as
before, or when **all** of the following hold:

1. `ReleaseCaller::applicationPassword()` is not null: `rest_get_authenticated_app_password()` gave a UUID,
   so WordPress signed the request in with an Application Password;
2. `ReleaseCaller::pushAllowed()`: the site's `wp-config.php` defines `COREX_ALLOW_RELEASE_PUSH` as `true`
   (read once, by `ReleaseCaller::ofThisSite()`);
3. `ReleaseCaller::overHttps()`: `is_ssl()`, or `wp_get_environment_type()` is `local`;
4. `mayManage()`. The route's permission callback has already asked; asked again for the reason the nonce is.

The refusals, all `403` and marked:

| Reason | When |
|---|---|
| `not_signed` | neither a valid nonce nor an Application Password (unchanged) |
| `push_off` | an Application Password, and the constant is not true; the message gives the line to add |
| `push_needs_https` | an Application Password over plain HTTP on a site that is not local |
| `not_allowed` | an Application Password of an account that may not install releases (only reachable if the permission callback let it through) |

A request WordPress signed in by cookie never carries an Application Password, so it still needs the nonce.

The release record and its activity entry gain `signed_in_with`, the password's name (its UUID if WordPress no
longer has it), for every event written during a request signed that way (`ReleaseRecord`, FR-014a).

`part_bytes` stays where the screen already reads it, in the overview; the `received` answer is unchanged.

Tested in `tests/Integration/Releases/ReleaseApplicationPasswordTest.php` (a whole installation signed this way
and the record naming the password; an upload in parts; `push_off` on each of the ten routes; no nonce and no
password; the screen's nonce with the constant off; an editor's password) and
`tests/Unit/Releases/ReleaseCallerTest.php` (the password's name, none for a cookie, the HTTPS rule).

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
