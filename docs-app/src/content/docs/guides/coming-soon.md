---
title: Coming soon mode
description: Show visitors a launch page while the site is built behind it, keep working on the real site, and give a client a link to review it.
---

Coming soon is one of the CoreX operations modes. While it is on, visitors get one page — the
coming-soon page, at the home URL — and the people building the site get the site. Examples use
the neutral **Acme** placeholder.

It is not Maintenance mode. Maintenance answers every visitor with a **503** and a fixed CoreX
notice, which suits an outage measured in minutes. Coming soon answers **200** with a page the
theme owns, which suits the weeks before a launch.

## What each request gets

| Who is asking | The home URL | Any other address |
|---|---|---|
| A visitor, signed out | The coming-soon page, **200** | A temporary redirect (**302**) to the home URL |
| Signed in, cannot edit posts | Same as a visitor | Same as a visitor |
| Signed in, can edit posts (`edit_posts`) | The real site | The real site |
| A browser that opened the preview link | The real site | The real site |

The admin, the login page, the REST API, AJAX and cron are never intercepted. `robots.txt` and
`/favicon.ico` are served as they always are, and the sitemap lists the home URL and nothing else.

:::note[What counts as the home URL]
The home path with nothing on it that WordPress acts on. `/?utm_source=launch` is the home URL.
`/?feed=rss2`, `/?p=12` and `/?s=term` are a feed, a post and a search, and are redirected.
:::

## Turn it on and off

Open **CoreX → Operations & Security → Environment & Maintenance**, choose **Coming soon**, tick
the acknowledgement, and apply. To launch, choose **Production** and type `PRODUCTION`.

From the command line:

```bash
wp corex mode get
wp corex mode set coming-soon --acknowledge --user=<login>
wp corex mode set production --phrase=PRODUCTION --user=<login>
```

The command follows the screen's rules: without the confirmation its mode needs, nothing changes
and it exits with status 1.

## While it is on

Anybody who can edit posts sees a bar on every front-end page — *"Coming soon is on. Visitors see
the coming-soon page, not this site."* — with a link to view the page as a visitor does, and, for
somebody who can change the mode, a link to Operations & Security. It cannot be dismissed. In the
admin the same message is in the toolbar.

## The preview link

For a client's stakeholders, who need to see the site and should not need an account.

On the same screen, **Create preview link**. The link is shown **once**, on the page that follows;
CoreX does not keep it. Whoever opens it is served the real site in that browser for **14 days**,
with a banner saying it is a private preview. It gives no access to the admin.

| You do this | What happens |
|---|---|
| **Regenerate link** | A new link, shown once. The old one stops working at its holders' next request. |
| **Revoke link** | No link. Every browser that used it is a visitor again at its next request. |
| Leave Coming soon | The link is removed. Coming back later starts with no link. |

Each of the three is recorded in the history with your name, and never the link.

:::caution[The link is a secret in an address]
It is in the web server's access log for the one request that opens it; CoreX then redirects to
the same address without it. If a link may have been exposed, regenerate it.
:::

## The page

The page is a block template named **`coming-soon`**.

- CoreX registers a default, which applies under any block theme.
- A theme replaces it with `templates/coming-soon.html`. `wp corex make:site` generates that file
  in every new client theme, so the page is in the client's repository from the first day.
- Either can be edited in the Site Editor.
- A theme with no block templates gets a self-contained CoreX page, with a 200.

Give it no header or footer part: every link in one would be redirected straight back to the page.

Load the page's own stylesheet and fonts on `corex_coming_soon_enqueue_assets`, which fires only
when the coming-soon page is the response:

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

The page's `<body>` carries `corex-coming-soon`, and no other page does.

To let one more address through — a privacy policy linked from the page — return `true` from
`corex_coming_soon_bypass` in the client's plugin:

```php
add_filter('corex_coming_soon_bypass', static function (bool $allowed): bool {
    return $allowed || is_page('privacy-policy');
});
```

On a multilingual site, name each language's home with `corex_coming_soon_is_home`, which receives
the answer so far and the request path.

## Search engines

The page is indexable. To keep it out of search results until launch, use WordPress's own
**Settings → Reading → Discourage search engines from indexing this site**; the page then carries
WordPress's `noindex` and no sitemap is published.

## Two limits

:::caution[It is not a confidentiality control]
The mode closes the front end. Content that is *published* can still be read through the REST API,
as on any WordPress site. Keep anything that must not be seen before launch in draft.
:::

:::caution[Purge a page cache when you switch]
Every response that depends on who is asking is marked non-cacheable, so a cache will not hand the
real site to a visitor. Headers cannot evict what a cache already holds, though: purge it when you
turn the mode on, and again when you turn it off.
:::
