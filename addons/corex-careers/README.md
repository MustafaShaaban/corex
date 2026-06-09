# Corex Careers

Job postings + a secure application flow with a pipeline. Optional add-on; builds on corex-core
(custom tables, blocks), Corex Mail (008), and the upload validator + captcha (012).

## Jobs

- A `corex_job` post type with **department / location / type** taxonomies and an archive.
- The **`corex/jobs`** block lists open positions as accessible cards (linked title + meta).

## Applications

- Apply via `POST /wp-json/corex/v1/careers/apply` (honeypot + captcha gated): name, email, cover
  letter, and a **CV**. The application is accepted only when the required fields are present and the
  CV passes the upload validator (allowed type/extension, size cap). A rejected application has **zero
  side effects**.
- A valid application is stored in the `corex_applications` custom table and **HR + the applicant are
  emailed** (Corex Mail).
- Applications move through a **pipeline** — `new → reviewing → interviewed → offer → hired / rejected`
  — and only valid transitions are allowed (`StatusFlow`).

## Tests

```bash
composer test              # headless: status flow, application service (validate->store->notify), jobs block
composer test:integration  # real ./wp: the application custom-table data path
```

> The validated CV file is stored via the boundary (`wp_handle_upload` to a protected location — never a
> caller path). Editor/visual + full apply-over-HTTP flows are best confirmed in a browser.
