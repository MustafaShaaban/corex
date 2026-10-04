---
title: Coming soon mode
description: Show visitors a launch page while the site is built behind it, let the people building it keep working, and give a client a link to review it.
audience: team
stability: stable
last_verified: 2026-10-04
---

# Coming soon mode

Coming soon is one of the CoreX operations modes. While it is on, visitors get one page — the
coming-soon page, at the home URL — and the people building the site get the site.

It is not Maintenance mode. Maintenance answers every visitor with a **503** and a fixed CoreX
notice, which is right for an outage measured in minutes. Coming soon answers **200** with a page
the theme owns, which is right for the weeks before a launch: a 503 held that long tells a search
engine the site is failing.

## What each request gets

| Who is asking | The home URL | Any other address |
|---|---|---|
| A visitor, signed out | The coming-soon page, **200** | A temporary redirect (**302**) to the home URL, with none of the page |
| Signed in, cannot edit posts (a subscriber, a customer) | Same as a visitor | Same as a visitor |
| Signed in, can edit posts (`edit_posts`) | The real site | The real site |
| A browser that opened the preview link | The real site | The real site |

The rules are tried in this order, and the first that matches decides:

1. **The admin, the login page, the REST API, AJAX and cron are never intercepted.** Nobody can be
   locked out, forms on the page still submit, and scheduled work keeps running.
2. An address carrying a valid preview link gives that browser preview access, then redirects to
   the same address without the link's secret.
3. A request that client code allowed through passes ([Letting one more address through](#letting-one-more-address-through)).
4. `robots.txt` and `/favicon.ico` are served as they always are.
5. Somebody who can edit posts and asks for the visitor view is served the coming-soon page.
6. Somebody who can edit posts is served the real site, with a notice bar.
7. A browser holding preview access is served the real site, with a preview banner.
8. The sitemap address answers with a sitemap listing the home URL and nothing else.
9. The home URL serves the coming-soon page.
10. Everything else, feeds included, is redirected to the home URL.

Three details worth knowing:

- **The home URL is the home path with nothing on it that WordPress acts on.** `/?utm_source=launch`
  is still the home URL. `/?feed=rss2`, `/?p=12` and `/?s=term` are not: they are a feed, a post and
  a search, and they are redirected.
- **`/login` and `/admin` keep working.** They are WordPress's own shortcuts to the login page and
  the admin. Where CoreX login protection hides the login route, it switches those shortcuts off,
  and they are then ordinary addresses.
- **The redirect is temporary and marked non-cacheable.** A browser or a proxy that remembered it
  would keep sending visitors to the home URL after launch.

## Turning it on and off

Open **CoreX → Operations & Security → Environment & Maintenance**, choose **Coming soon**, tick
the acknowledgement that visitors will see the coming-soon page, and apply. The change is recorded
in the history with your name.

To launch, choose **Production**. That is the existing launch switch: it shows the readiness result
and asks you to type `PRODUCTION`.

From the command line:

```bash
wp corex mode get
wp corex mode set coming-soon --acknowledge --user=<login>
wp corex mode set production --phrase=PRODUCTION --user=<login>
```

The command follows the same rules as the screen. Without the confirmation its mode needs, nothing
changes and it exits with status 1. Setting the mode the site is already in exits with status 0.
See the [CLI README](../../../packages/cli/README.md) for every option.

Exactly one mode is in force, so Coming soon and Maintenance cannot be on together.

## What you see while it is on

If you can edit posts, every front-end page carries a bar: **"Coming soon is on. Visitors see the
coming-soon page, not this site."** It has a link to **view the coming-soon page** as a visitor
sees it, and — only if you can change the mode — a link to Operations & Security. It cannot be
dismissed. In the admin, the same message is in the toolbar.

The visitor view is the coming-soon page exactly as a signed-out visitor at the home URL gets it:
no WordPress toolbar and no bar. Its address is the home URL with `?corex_visitor_view=1`. That
argument does nothing for somebody who is a visitor already.

## The preview link

A client's stakeholders need to see the site before launch and should not need an account for it.

On **Operations & Security → Environment & Maintenance**, while the site is in Coming soon, the
**Preview link** card has **Create preview link**. The link is shown **once**, on the page that
follows. Copy it then: CoreX does not keep it, so it cannot be shown again.

Whoever opens the link is served the real site in that browser for **14 days**, with a banner
saying it is a private preview the public cannot see yet. Opening the link again renews the 14
days. The link gives no access to the admin and no WordPress capability of any kind.

| You do this | What happens |
|---|---|
| **Regenerate link** | A new link is shown once. The old link stops working, and every browser that used it is a visitor again from its next request. |
| **Revoke link** | There is no link. Every browser that used it is a visitor again from its next request. |
| Leave Coming soon for any other mode | The link is removed. Coming back to Coming soon later starts with no link. |

Creating, regenerating and revoking are each recorded in the history with your name. The link
itself is never recorded.

What is stored, and what is not:

- The site keeps a hash of the link's secret, keyed with one of the site's own salts. The link
  cannot be read back out of the database.
- The browser's cookie holds an expiry and a signature. It does not hold the link, so a cookie that
  leaks gives away one browser's access and not the link.
- **The link's secret is in the address when it is opened**, so it appears in the web server's
  access log for that one request. CoreX then redirects to the same address without it. If a link
  may have been exposed, regenerate it.
- Changing the site's authentication salts ends the preview link.

## The page itself

The coming-soon page is a block template named **`coming-soon`**.

- CoreX registers a default under that name. It applies under **any** block theme, whether or not
  the theme is related to the Corex theme.
- A theme replaces the default by shipping `templates/coming-soon.html`. `wp corex make:site`
  generates that file in every new client theme.
- Either can be edited in the Site Editor, and a copy saved there replaces both.
- Under a theme with no block templates at all, CoreX serves a self-contained page instead, with a
  200.

Give the page no header or footer part. Every link in one would be redirected straight back to
the page.

### Loading the page's own assets

A designed launch page has its own stylesheet and fonts. Load them on
`corex_coming_soon_enqueue_assets`, which fires when front-end assets are queued and **only** when
the coming-soon page is the response:

```php
add_action('corex_coming_soon_enqueue_assets', static function (): void {
    wp_enqueue_style(
        'acme-coming-soon',
        get_stylesheet_directory_uri() . '/assets/css/coming-soon.css',
        [],
        wp_get_theme()->get('Version'),
    );
});
```

The page's `<body>` carries the class `corex-coming-soon`, and no other page does. Scope the page's
styles to it.

### Letting one more address through

A launch page often links to one more public page — a privacy policy, say. Return `true` from
`corex_coming_soon_bypass` for the requests that should pass:

```php
add_filter('corex_coming_soon_bypass', static function (bool $allowed): bool {
    return $allowed || is_page('privacy-policy');
});
```

Only a strict `true` allows a request. Put this in the client's own plugin.

On a multilingual site, name each language's home as a home URL with `corex_coming_soon_is_home`.
It receives the answer so far and the request path:

```php
add_filter('corex_coming_soon_is_home', static function (bool $isHome, string $path): bool {
    return $isHome || in_array($path, ['en', 'ar'], true);
}, 10, 2);
```

## Search engines

The coming-soon page is indexable. Nothing in the mode asks a crawler to stay away, and the
sitemap tells it about the home URL.

To keep the page out of search results until launch, use WordPress's own setting: **Settings →
Reading → Discourage search engines from indexing this site**. The page then carries WordPress's
`noindex`, and no sitemap is published: the sitemap address is redirected like any other.

## Two limits to know before relying on it

**It is not a confidentiality control.** The mode closes the front end. Content that is
*published* in WordPress can still be read through the REST API, as on any WordPress site. Keep
anything that must not be seen before launch in draft.

**A page cache in front of the site has to be purged when you switch.** CoreX marks every response
that depends on who is asking — the real site served to an editor or a preview holder, and every
redirect — as non-cacheable, so a cache will not hand the real site to a visitor. What headers
cannot do is evict what a cache already holds: a page cached while the site was open can still be
served after the mode is switched on. Purge the cache when you turn the mode on, and again when
you turn it off.

## Related

- [Operations & Security](operations-and-security.md) — the screen, the other modes, and the
  production readiness checks.
- [Updating CoreX in a client site](../05-deployment/updating-a-client-site.md) — the client theme
  that owns the page is client-owned, so a framework update does not touch it.
