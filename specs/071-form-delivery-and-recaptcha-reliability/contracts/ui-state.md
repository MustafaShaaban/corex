# UI State Contract: Delivery Outcomes & Captcha Settings

How the six delivery states and the captcha settings states are presented. Presentation-only; the
truth is the persisted `NotificationDelivery` (see data-model.md).

## Delivery states in the Submissions inbox

Seven presented states — the six `NotificationDelivery` statuses that reach the inbox, plus the
legacy no-record case. Each conveyed by **text + shape + accessible name**, never colour alone
(FR-Sc / SC-007 / WCAG 2.2 AA 1.4.1).

| State | Row tone (`is-*`) | Label | Icon | Accessible name |
|---|---|---|---|---|
| accepted | `is-success` | Saved — notification accepted | check | "Saved, notification accepted for delivery" |
| captured | `is-info` | Saved — notification captured | inbox | "Saved, notification captured in development" |
| queued | `is-info` | Saved — notification queued | clock | "Saved, notification queued" |
| failed | `is-warning` | Saved — notification failed | alert | "Saved, notification failed" |
| rejected | `is-warning` | Saved — notification rejected | slash | "Saved, notification rejected before sending" |
| not_attempted | `is-neutral` | Saved — no notification | minus | "Saved, no notification was required" |
| legacy-unavailable | `is-neutral` | Saved — delivery outcome unavailable | history | "Saved before delivery tracking; outcome unavailable" |

- Tones map to existing `--corex-admin-*` tokens via the established `is-{tone}` row convention
  (`corex-admin-shell.css`). No new colour values.
- "accepted" is deliberately worded as *accepted for delivery*, not *delivered* — a transport
  accepting a message is not proof it reached an inbox (FR-015).

## Submission detail

- The delivery status block shows the label above, the `attemptedAt` timestamp (localised via
  `wp_date`), and the `safeReason` for any non-success state.
- The attempt link appears **only** when `attemptId` is non-null *and* the current actor passes the
  Email Studio view ability (FR-022). Absent either condition, no link and no empty affordance.
- `safeReason` is the redacted string from `NotificationDelivery`; it never contains a server name,
  credential, path, or internal id (FR-019).

## Submission timeline

The `notification` stage renders one entry per attempt: outcome label + timestamp, plus the attempt
link under the same permission rule. Legacy `flow.submitted` rows (old shape) render with their
hydrated stage/outcome rather than blank.

## Delivery filter

The inbox filter offers the seven states above as options, using the approved `CorexSelect`
component (never a native `<select>`, per spec 069). Filtering is server-side and bounded.

## Captcha settings states

The settings surface honestly distinguishes five states (FR-026):

| State | When | What the admin sees |
|---|---|---|
| disabled | `captcha.driver = none` | "Spam protection is off. Only the honeypot guards forms." |
| unconfigured | driver set, key/secret missing | "reCAPTCHA is selected but not configured." + which field is missing |
| configured | driver + key + secret present | Active summary: effective threshold, allowed hostnames, action derivation note |
| unavailable | the captcha add-on is inactive | "The Captcha add-on is inactive; forms fall back to the honeypot." |
| error | last test verification failed | The safe failure reason from the test action |

- The threshold field shows the default (0.3) and states it is a starting point to monitor, not a
  universally correct value (FR-010).
- Help text states plainly: only protected CoreX forms are covered; the secret never reaches the
  browser; local/staging hostname expectations.

## Accessibility (all surfaces)

Keyboard-operable; visible focus; announced state changes via the existing `role="status"` regions;
dark + light + RTL + 375px verified; reduced-motion respected. Screenshots for evidence come from
controlled fixtures, never live customer data.
