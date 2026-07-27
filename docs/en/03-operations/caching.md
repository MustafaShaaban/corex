# Caching in CoreX

CoreX caches a small number of things to avoid recomputing them, and gives you one place to see
every caching layer on your site and act on the ones it can.

**CoreX works fully with no caching infrastructure at all.** No Redis, no Memcached, no CDN, no
page-cache plugin, no Docker. That is the normal setup on shared hosting and it is the one CoreX is
built for. Everything below is about being honest regarding what exists, not about persuading you to
install anything.

## The seven layers

`CoreX → Operations & Security → Cache & Performance` lists every layer, what it does, whether it is
active, and whether CoreX can do anything about it.

| Layer | What it is | Can CoreX clear it? |
|---|---|---|
| **Visitor browsers** | Copies your visitors' browsers keep | **No.** Nothing can — see below |
| **PHP OPcache** | Compiled PHP held in memory | No — that belongs to deployment |
| **This request's cache** | What WordPress remembers within one page load | Yes |
| **Persistent object cache** | Redis/Memcached, if you run one | Only via WordPress — see below |
| **CoreX application cache** | CoreX's own cached values | Yes |
| **Page cache** | Whole pages served without WordPress | Only through a provider |
| **CDN** | Assets served near your visitors | Only through a provider |

Each state is checked, never assumed. A Redis container running on your server is **not** reported
as active unless WordPress is actually using it — those are different facts, and the second is the
one that affects your site.

Where CoreX cannot look, it says **unknown** rather than guessing. Many hosts disable OPcache
inspection; "unknown" is the truthful answer there, and reporting "off" would send you looking for a
problem you do not have.

## CoreX cannot clear a visitor's browser cache

This is the one worth stating outright, because it is what most people mean when a change does not
appear.

No website can empty a browser's cache — the browser owns it. What CoreX does instead is change the
version stamped on each asset whenever the asset changes, so browsers see a new address and fetch
the new file. That is what actually solves "I updated the CSS and it still looks old", and it is
automatic.

If a visitor still sees something old, it is almost always a page cache or a CDN, both of which are
listed above.

## What "clear the cache" clears

```bash
wp corex cache:clear
```

Clears CoreX's own safe caches — build metadata and computed counts — and reports exactly what it
did:

```text
cleared:     corex_asset_manifest
skipped:     corex_form_submission_counts — Was not cached.
skipped:     corex_throttle_ — This holds active security state…
Success: 1 cache entry cleared.
```

It always tells you what it **skipped**, not just what it cleared. If you clear everything and a
rate limit is still in force, the reason is on screen rather than left for you to wonder about.

### Scopes

```bash
wp corex cache:clear --scope=assets
```

| Scope | Clears |
|---|---|
| `corex` *(default)* | CoreX's own safe caches |
| `assets` | Build manifests and asset metadata |
| `runtime` | This request's object cache |
| `object` | The whole object cache — **requires `--yes`** |
| `expired-transients` | Already-expired CoreX transients |
| `page` | Via a page-cache provider, if one is registered |
| `cdn` | Via a CDN provider, if one is configured |

An unrecognised scope is refused with the list of valid ones — never guessed at.

**`--scope=object` affects every plugin on your site**, not just CoreX, which is why it needs
`--yes`. And if your site keeps transients in a persistent object cache, CoreX **refuses** it
entirely: flushing would also remove CoreX's rate limits and spent-token records. Run
`wp cache flush` yourself if that is genuinely what you want — that is WordPress's operation, with
WordPress's consequences.

### Other commands

```bash
wp corex cache:status   # every declared cache entry and its classification
wp corex cache:doctor   # what is caching, what CoreX can see, what it cannot
```

Neither exposes a password, token, key or cached value.

## What is never cleared by a cache action

Some things CoreX stores in fast places are not caches, and no cache operation at any scope removes
them:

- **Rate limits.** Failed-login and request throttling. Clearing these would not free space; it
  would remove protection, usually at the exact moment you are clicking buttons because something is
  going wrong.
- **Spent captcha tokens.** Removing these would let a used token be replayed.
- **Pending confirmations.** An import, migration or bulk action you are part-way through
  confirming.
- **Records.** Submissions, audit entries, notifications, access requests, email delivery records,
  editorial history, mode history. These are data. That some are read through a cache does not make
  them disposable — they are governed by retention, not by clearing caches.

This is enforced in code, not by convention: every cached value is declared with a classification,
and the clear path walks that list rather than matching a key pattern. A pattern match would delete
the rate limits, because they look exactly like cache from the outside.

## Redis and object caching

Optional. If you install an object-cache drop-in, CoreX will use it automatically and report it as
active. If you do not, CoreX uses WordPress transients and works exactly as well — slower under
heavy load, identical in behaviour.

CoreX does not install cache plugins, does not ship a Redis client, and will never enable one on a
production site for you.

## Page caches and CDNs

CoreX does not implement a page cache. It detects one and integrates with it through a provider,
so purging is done by whatever you already run.

The same applies to CDNs. **CoreX will not reuse a credential you granted for something else** — an
API token given to Insights for analytics is not a token for purging your cache, and using it that
way would be using a key for a door it was not cut for.

## For developers

```php
use Corex\Cache\CacheManager;
use Corex\Cache\CacheScope;

$value = $cache->store()->remember( 'forms', 'counts', MINUTE_IN_SECONDS, fn () => $this->count() );

$outcome = $cache->clear( CacheScope::Corex );
```

`remember()` takes a best-effort lock so two simultaneous misses do not both recompute. Best-effort
is meant literally: without a persistent object cache the lock is weaker, and work that must run
exactly once needs a real lock rather than this.

Adding a cached value means declaring it in `CacheRegistry` with its owner, classification, lifetime
and invalidation path. That is deliberate friction: an undeclared cache is one no clear path knows
about and no operator can see.

Add-ons can declare their own through the `corex_cache_registry` filter, and page-cache or CDN
providers register through `corex_page_cache_provider` and `corex_cdn_provider`.
