# Corex Bookings

A "request a call with one of our leaders" flow. Optional add-on; builds on corex-core (custom
tables), Corex Mail (008), and Corex Captcha (012).

## Flow

1. Configure your leaders (`bookings.leaders` — a list of `{id, name, email}`).
2. A visitor picks a leader and submits name + email (+ phone, preferred time, message) via
   `POST /wp-json/corex/v1/bookings/request` (honeypot + captcha gated).
3. A valid request (known leader + valid contact) is stored in the `corex_call_requests` custom table,
   and the **leader is notified** + the **visitor confirmed** (Corex Mail). A rejected request has
   **zero side effects**.

## Tests

```bash
composer test              # headless: leader directory + the request service (validate -> store -> notify)
composer test:integration  # real ./wp: the call-request custom-table data path
```

> Real availability calendars, time-zone handling, and reminders are deferred. The full request-over-HTTP
> flow + email rendering are best confirmed in a browser.
