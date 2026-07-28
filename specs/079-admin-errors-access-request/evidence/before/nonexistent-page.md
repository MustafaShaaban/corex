# A CoreX page that does not exist tells an administrator they lack access

Read from the running install at `corex.local` on 2026-07-28, at `7fa8215` (merged `main`, spec 078
included). Signed in as `admin`, a full administrator holding `manage_options`.

## What was asked

```
GET http://corex.local/wp-admin/admin.php?page=corex-nonexistent
```

## What came back

```
HTTP 403
```

> **You don't have access to this area**
>
> Your role doesn't include the `manage_options` capability CoreX requires. Ask a site administrator
> to grant it through a roles plugin if you need this screen.
>
> [Back to Dashboard]
>
> **Why do you need access?** … [Request access]

## Why this is wrong

Three separate ways.

1. **The statement is false.** The viewer is an administrator. Their role *does* include
   `manage_options`. The page told them the opposite, at a status code that says the server
   understood and refused.
2. **The status is wrong.** Nothing was refused. The resource does not exist. 403 sends an operator
   looking for a permissions problem that is not there — and a capability audit is an expensive
   place to send someone who made a typo.
3. **The remedy offered does not exist either.** The surface renders the Access Request form for a
   screen with no ability behind it. Today that form posts to a REST endpoint and shows raw JSON
   (see `raw-json-navigation.md`). After spec 079 Phase 2 it would work — and quietly create a real,
   auditable access request for a page nobody can ever grant.

## Where it comes from

`AccessDeniedGate::intercept()` matches on the prefix alone:

```php
if ($page === '' || ! str_starts_with($page, 'corex-')) {
    return;
}
```

`admin_page_access_denied` is WordPress's hook for "this admin page is not available to you", and
WordPress fires it for **both** causes — a registered page the user cannot open, and a page that was
never registered. The gate treats every `corex-`-prefixed firing as the first cause.

The check that distinguishes them is available at that moment: the page is in
`$GLOBALS['_registered_pages']` if it exists. The gate never asks.

## Control

```
GET http://corex.local/wp-admin/admin.php?page=corex-settings&tab=bogus
→ HTTP 200, the CoreX shell renders.
```

An unknown *tab* on a real page degrades correctly. Only an unknown *page* misreports.

## What was expected, and was not found

The spec assumed this route reached WordPress's own *"Sorry, you are not allowed to access this
page."* white box — the string SC-005 names. It does not; the gate intercepts first. The white box
is still worth keeping out of CoreX surfaces, but it is not the defect here. **The defect is that
CoreX confidently answers the wrong question**, which is the failure mode this project keeps
finding and is the reason reproduction comes before specification.
