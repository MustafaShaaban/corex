---
title: The first installation, by hand
description: Put CoreX on a host with no command line once, so that every release after it is installed from CoreX → Releases — and move a site that has been updated by unpacking zips to the screen.
audience: ops
stability: draft
last_verified: null
---

# The first installation, by hand

[Releases from the admin](./releases-from-the-admin.md) installs a release on a site that runs
CoreX. It cannot put CoreX on a site that does not: there is no screen to open yet. So the first
installation on a host is done by hand, once. This page is that once, written so that it leaves
the site ready for every release after it to be one upload.

It has two parts. [A new site](#a-new-site) is a host that has nothing on it.
[A site that is already live](#a-site-that-is-already-live) is one that has been updated by
unpacking a zip over the release before, and moves to the screen.

Examples use the neutral **Acme** placeholder and a cPanel host. The steps that are the host's
own (the database, the PHP version, HTTPS) are in
[Deploy to cPanel shared hosting](./cpanel-shared-hosting.md) and are not repeated here.

> **Nothing here has been run on a shared host by CoreX's own tests.** `last_verified` stays
> `null` until this page has been followed on one.

## A new site

### 1. Build the package

In the client's repository, at the release you are shipping:

```bash
npm ci && npm run build
# build each client theme that has an asset pipeline
npm run build:dist -- --client=acme --zip
```

Name any plugin that is neither CoreX's nor the site's own with `--with-plugin=<folder>`, so it
is part of the release from the start
([Shared-host dist artifact](./shared-host-dist.md#a-third-party-plugin-in-the-package)).

The build verifies the package and stops if it does not load. It writes
`corex-release-acme-<version>-<build time>.zip` in the repository root. The zip holds WordPress,
CoreX, the site's own plugin and theme, and `corex-release.json`, which says what the package
is. It holds no `wp-config.php` and no `.htaccess`.

### 2. Prepare the host

The database and PHP 8.3 with the `zip` extension: steps 2 and 3 of
[Deploy to cPanel shared hosting](./cpanel-shared-hosting.md). The `zip` extension is not
optional here. Without it the Releases screen cannot open a package.

### 3. Put the package on the host

In the host's file manager, upload the zip into the site's web root and extract it there, so
that `wp-load.php`, `wp-content/` and `corex-release.json` are in the web root itself and not in
a folder inside it. Delete the zip afterwards.

Into an empty web root. Extracting adds and overwrites and removes nothing: over an older
release it leaves that release's files beside the new one's. That is the fault the Releases
screen exists to end, and this is the last time the site is exposed to it.

Leave `corex-release.json` where it is. Until the site's first installation from the admin it
is how the site knows which release it runs and which client it is, and so which packages it
may be given.

### 4. Write `wp-config.php`

Create it from `wp-config-sample.php`, with the database's details and fresh salts, as step 5 of
the cPanel guide has it. Then decide this, now, while the file is open:

```php
// Nobody signed in to the admin installs or edits plugins and themes.
define( 'DISALLOW_FILE_MODS', true );

// A release may still be installed from CoreX → Releases.
define( 'COREX_ALLOW_RELEASE_INSTALL', true );
```

With the first line and without the second, the Releases screen reads packages and refuses to
install them. With neither, it installs, and so do WordPress's own installers.
[Releases from the admin](./releases-from-the-admin.md#a-site-that-sets-disallow_file_mods) says
what the second line gives and to whom.

### 5. The database and the uploads

A new site: open its address and run WordPress's installer. A site that is moving: import its
database and copy `wp-content/uploads/` across, as step 6 of the cPanel guide has it. Neither is
in the package, and neither is ever a release's.

### 6. Open the admin once

Sign in. On a new site, switch on the CoreX theme and plugins. The first admin page makes
CoreX's tables: nothing has to be typed anywhere for that.

Save **Settings → Permalinks** once, so WordPress writes its `.htaccess`.

### 7. Read the Releases screen

Open **CoreX → Releases** and read two sentences under **Running now**.

| It says | It means |
|---|---|
| This site is running CoreX `<version>`, built for acme. | The site read `corex-release.json`. It will take acme's packages and refuse anybody else's |
| This site does not say which release it is running. | `corex-release.json` is not in the web root. Put it back from the package. Otherwise the first installation asks for the client's name to be typed, and has no record of which folders the running release owns |
| Nothing on this host stands in the way of installing a release. | Ready |
| A release cannot be installed on this host yet: … | Each thing in the way is listed with what to do. [The table](./releases-from-the-admin.md#what-the-host-has-to-allow) has them all |

When both sentences are the good ones, the site is ready. Every release from here on is
[Releases from the admin](./releases-from-the-admin.md): build, send, read, save the recovery
link, type the version.

## A site that is already live

A site that has been updated by unpacking each release's zip over the one before moves to the
screen like this. It does not have to be cleaned first.

### 1. Take the release that has the screen, the old way, one last time

The Releases screen installs from CoreX 0.45.0. A site on an earlier release gets that release
the way it got the others, once more, whatever clean-up that takes on the host. After it, the
file manager is not needed for a release again.

### 2. Add the line, if the site sets `DISALLOW_FILE_MODS`

In `wp-config.php`, as in step 4 above. Open **CoreX → Releases** and read the two sentences of
step 7. A site unpacked from a package built by CoreX 0.44.0 or later has a `corex-release.json`
in its web root that names its client and the folders the release owns, which is all the screen
needs.

### 3. Build the next release with the builder, and nothing else

```bash
npm run build:dist -- --client=acme --with-plugin=wp/wp-content/plugins/fluent-smtp --zip
```

If the site's own packaging script copied a plugin into `dist/` after the build, zipped the
folder itself, or wrote a list of files to delete after the upload: none of that is needed. The
plugin is named with `--with-plugin`; `--zip` makes the zip, under the name the screen keeps;
and nothing has to be deleted by hand, for the reason below.

### 4. Install it from the screen

As [Releases from the admin](./releases-from-the-admin.md#install) has it.

What that first installation does to what unpacking left behind:

- **Inside the folders a release owns, everything that is not the release's goes.** Each CoreX
  plugin and add-on, the site's plugin and theme, `wp-content/vendor` and `wp-content/packages`
  is moved out whole and the package's folder moved in. Files left by releases before, pattern
  files of an earlier theme among them, are in the kept previous release and not on the site.
- **A plugin now named with `--with-plugin`** is replaced by the package's copy the same way.
  Its settings are in the database and are not touched.
- **Outside those folders nothing is removed.** A stray file in the web root from an older
  package (a root `vendor/` from CoreX 0.43.3 or earlier, say) is still there, and is deleted by
  hand once. `corex-release.json` in the web root stays and goes stale: the site records each
  installation itself from now on and no longer reads it.

## See also

- [Releases from the admin](./releases-from-the-admin.md) — every release after this one.
- [Deploy to cPanel shared hosting](./cpanel-shared-hosting.md) — the host's own steps.
- [Shared-host dist artifact](./shared-host-dist.md) — what the package holds and how it is built.
