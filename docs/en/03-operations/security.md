# Operations and Security

CoreX Operations & Security is the owner-facing control plane for launch readiness, operating mode, login protection,
lockouts, recovery, and security activity.

## Production launch

Production mode is guarded by the same readiness snapshot shown on screen. Before switching to Production:

1. Review every launch checklist item.
2. Resolve blocking checks when possible.
3. If you intentionally override blockers, type `PRODUCTION` exactly in the confirmation field.

CoreX records the mode transition in the operations-mode history. Maintenance mode remains separately confirmed because
it affects real visitors.

## Login protection and recovery

Login protection can:

- rate-limit failed login attempts by hashed identity and network evidence;
- block default login endpoints behind a custom login slug;
- retain only bounded login-attempt evidence;
- release lockouts through the recovery command.

If an owner is locked out or the protected route is misconfigured, run:

```bash
wp corex security reset-login
```

The command disables the protected login gates, releases active lockouts, and prints the restored `wp-login.php` URL.
It does not change users, roles, passwords, content, or unrelated CoreX settings.

For emergency request-level bypasses, define `COREX_LOGIN_UNGUARD` only long enough to regain access, then remove it.

## Access requests

When a signed-in user cannot open a CoreX admin area, the denied screen shows a real request-access form. The request
is stored through the Access workflow with:

- the requested CoreX ability;
- the requester;
- a required reason;
- a seven-day expiry.

Administrators review requests in CoreX Access & Abilities. Approval grants the requested CoreX-owned ability; denial
records the decision without granting access.

Native WordPress capabilities and third-party role plugins remain compatibility inputs. CoreX-owned abilities are the
only states edited by the CoreX Access workflow.
