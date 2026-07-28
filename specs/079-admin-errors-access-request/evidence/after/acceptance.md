# Acceptance matrix — Spec 079

Captured on `corex.local` on 2026-07-28 at `14fcd3a` plus the working tree. Overflow is
`document.documentElement.scrollWidth - clientWidth`, measured on the real page, not asserted from a
container. Console counts are page errors, not warnings.

| Screenshot | Width | Dir | Theme | Overflow | Console |
|---|---|---|---|---|---|
| `denied-form-ltr.png` | 1280 | LTR | light | 0 | 0 |
| `denied-form-rtl-375.png` | 375 | **RTL** | light | 0 | 0 |
| `denied-form-dark.png` | 1280 | LTR | **dark** | 0 | 0 |
| `denied-invalid.png` | 1280 | LTR | light | 0 | 0 |
| `denied-sent.png` | 1280 | LTR | light | 0 | 0 |
| `denied-sent-rtl-375.png` | 375 | **RTL** | light | 0 | 0 |
| `admin-waiting-card.png` | 1280 | LTR | light | 0 | 0 |
| `admin-requests-panel.png` | 1280 | LTR | light | 0 | 0 |
| `admin-requests-panel-rtl-375.png` | 375 | **RTL** | light | **1** | 0 |
| `not-found-404.png` | 1280 | LTR | light | 0 | 0 |

## The one non-zero, and why it is not this spec's

`corex-access` overflows the document by 1px in RTL at 375px. It was measured on the same install
with this branch stashed, before any spec 079 change:

```
corex-access               overflow 1
corex-access&tab=matrix    overflow 1
```

It is present on **every** tab of that screen, including Overview, which carries none of the markup
this spec adds; hiding `.corex-access__panels` does not change it. So it is the same class of defect
the CI-lint/RTL branch cleared for `corex-addons` and `corex-settings`, still outstanding on
`corex-access`, and it is carried forward rather than folded in here — a 1px shell fix on a third
screen does not belong in a diff about access requests, and hiding it inside one is how it stops
being visible as work.

Every surface this spec **adds** measures 0, including the denied surface in all four of its states
at 375px RTL.

## What the screenshots show

- **`denied-form-ltr`** — the request form, action `admin-post.php`.
- **`denied-invalid`** — submitted empty: the field error, focus on the field, the form still there,
  and no request created.
- **`denied-sent`** — the confirmation, with the date rendered by the spec 076 contract
  (*"28 July 2026 at 12:04 AM"*), no form, and none of `operation_id`, `state`, `affected_ids` or
  `audit_event_id`. Compare `../before/after-request-raw-json.png`.
- **`admin-waiting-card`** — the Overview tab saying somebody is waiting, with a link to the panel.
  Absent entirely when nobody is.
- **`admin-requests-panel`** — who asked, for what, when and why, with Approve and Deny.
- **`not-found-404`** — `?page=corex-nonexistent` as an **administrator**: HTTP 404, no capability
  sentence, no request form. Compare `../before/nonexistent-page.md`.
