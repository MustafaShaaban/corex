---
title: Releases from the admin
description: Install a release on a host with no command line, from CoreX → Releases — what to build, what is replaced, the way back, and what this does not do.
audience: ops
stability: draft
last_verified: null
---

# Releases from the admin

For a site on a host with no command line: shared hosting where the only ways in are a file
manager and wp-admin. A release is built on your machine as one zip, sent to the site from
**CoreX → Releases**, checked there, and put in place with the previous release kept to go back
to. Nobody opens the host's file manager.

Examples use the neutral **Acme** placeholder, the release `1.2.4` and the previous one `1.2.3`.

> **This has not been run on a shared host.** It is tested on real WordPress, in a browser, on
> nginx with php-fpm and on PHP's own server. Make the first installation on a real host one of
> a release that changes nothing important, with the recovery link saved and the host's backup
> taken. `last_verified` stays `null` until somebody has.

It does not replace the first installation. A screen cannot be offered where CoreX is not
running: see [The first installation, by hand](./first-installation-by-hand.md). A site with a
command line or a pipeline keeps what it has ([Shared-host dist artifact](./shared-host-dist.md),
[Azure Pipelines](./azure-pipelines.md)).

## What it is not

- **Not a backup.** It copies CoreX's own tables before a release changes them and keeps the
  previous release's folders. It does not copy the database's content, the uploads or WordPress.
  A backup plugin or the host's backups are what restore a broken site. The screen says so.
- **Not an updater.** Nothing is downloaded and nothing checks for new versions. The package is
  the one your repository built.
- **Not signed.** The site checks that a package is whole and is built for it. It cannot check
  who built it. Who may install is decided by who holds the ability, below.

## Before the first time

### Who may

The screen and every one of its actions need the ability `corex_manage_releases`. Administrators
hold it; it can be denied to a role on **CoreX → Access & Abilities**. On a network it also
needs a super administrator, on the main site.

### A site that sets `DISALLOW_FILE_MODS`

A site set not to change its own files refuses to install, and the screen says so when it is
opened. Add one line to `wp-config.php`, once:

```php
define( 'COREX_ALLOW_RELEASE_INSTALL', true );
```

`DISALLOW_FILE_MODS` stays as it is. With both, WordPress's own plugin and theme installers,
updaters and editors stay off, and a release can be installed from the Releases screen.

Know what the line gives. `DISALLOW_FILE_MODS` says nobody signed in to the admin puts code on
the site. The line makes one exception: whoever holds `corex_manage_releases` can replace the
site's code with a package that describes itself as this site's. Only somebody who can edit
`wp-config.php` can write the line, which is who that setting trusts already.

### What the host has to allow

Opened on a host that cannot install, the screen lists each thing in the way:

| The screen says | What to do |
|---|---|
| The site is set not to change its own files | The line above |
| PHP cannot open a zip | Ask the host to turn on PHP's `zip` extension |
| A folder cannot be written by the site | PHP must be able to write in `wp-content`, `wp-content/plugins`, `wp-content/themes`, `wp-content/mu-plugins` and the folder WordPress is in |
| Not enough room | An installation unpacks the release beside the running one before either moves, and keeps the previous one afterwards |
| Plugins or themes are somewhere other than a package holds them | Nothing: a site that moved or renamed `wp-content`, its plugins folder or its themes folder cannot install from the admin |

A host that will not say how much room it has is not refused for it. A host's limit on the
number of files is not readable from PHP: the previous release's folders count against it until
the next installation replaces them.

## Build the package

In the client's repository, at the release you are shipping:

```bash
npm ci && npm run build
# build each client theme that has an asset pipeline, as before
npm run build:dist -- --client=acme --zip
```

That writes `corex-release-acme-1.2.4-<build time>.zip` in the repository root. Give the site
that file, under that name: a zip named any other way is not kept.

A plugin that is neither CoreX's nor the site's own (a mail plugin, say) travels in the package
when it is named to the builder:

```bash
npm run build:dist -- --client=acme --with-plugin=wp/wp-content/plugins/fluent-smtp --zip
```

It is then a folder of the release like the others: measured by the builder, checked on the
site, and replaced whole. Do not copy a plugin into `dist/` after the build and zip it yourself.
Such a package installs, and leaves the site's copy of that plugin as it is, because nothing
measured it. [Shared-host dist artifact](./shared-host-dist.md) has the builder in full.

Build from the copy of the plugin the site runs now. A plugin updated in the site's admin after
the package was built is newer on the site than in the package, and installing the package
puts it back to the package's copy. The statement names each such plugin or theme before you
type the version (see [Install](#install)).

## Install

1. **Open CoreX → Releases.** It says which release the site runs and for whom, and whether
   anything on the host stands in the way.
2. **Send the package.** Choose the zip and press **Send to the site**. It goes in parts, sized
   to what the host accepts in one upload, and goes on from where it stopped if the connection
   drops. A package you put in `wp-content/corex-releases/incoming/` yourself is listed without
   being sent.
3. **Read the statement.** The release, when it was built and for which client, what it
   replaces, how many files. Reading changes nothing on the site. A package that is wrong is
   refused here, in words, and removed: see [Refusals](#refusals). A plugin or theme the
   package holds an older version of than the site has is listed here with both versions, as
   the header of its main file (a theme's `style.css`) gives them. CoreX's own plugins and
   theme are not listed: they carry the release's version, and an older release is asked about
   in step 6. To keep the site's newer copies, build the package again from them.
4. **Press Install this release….** Nothing is installed yet. The next panel gives the
   **recovery link** and says what installing does.
5. **Save the recovery link.** Copy it or press **Save it as a file**. It is shown once. It is
   what puts the previous release back if the admin does not load afterwards.
6. **Type the version and press Install.** An older release than the one running needs a box
   ticked as well. So does a package that puts plugins or themes back to older versions: the
   panel lists them again, and the site refuses the installation until that box is ticked. A
   site that cannot say whose it is asks for the client's name, as the statement gives it.
7. **Keep the page open** until it says the release is installed. It lists what changed in the
   database, or says nothing needed to.

### What the site does, step by step

| Step | What happens | Visitors |
|---|---|---|
| Unpack | The release's folders are unpacked into `wp-content/corex-releases/staging/` | Served as before |
| Check | Each folder is counted and hashed and compared with what the package says it holds | Served as before |
| Copy tables | Each of CoreX's tables is copied as it is | Served as before |
| Put in place | The release kept from the installation before is set aside to `discarded/`; each folder of the running release is moved to `previous/` and the unpacked one moved in | Held off |
| Database | The release just installed brings the database to itself and lists what moved | Held off until it ends |

Each step is one or more short requests, so a host that cuts a request off after some seconds is
not in the way. Until the first folder of the fourth step moves, nothing of the site has
changed, and the installation can be given up.

While visitors are held off they get WordPress's own "briefly unavailable for scheduled
maintenance" answer, for the seconds between the folders moving and the database step ending.

The screen's own requests get through that answer with the installation's key, which goes in
each request's address as well as in a header: some shared hosts do not hand a request's headers
to PHP. If the site still answers nothing the screen can read, the progress panel says it is
waiting, and the screen goes on asking for up to eleven minutes. WordPress lets every request in
by itself ten minutes after the folders moved, so the installation then finishes without
anybody pressing anything. Keep the page open until it says the release is installed.

The record of an installation says how its last request got through (`key_from` in
`corex-releases/log.json`): `header`, `server_headers` (the header was not handed to PHP, and
was read where the web server keeps it), `address`, or `not_needed` (WordPress had already
let requests in).

### If the page is closed, or the connection drops

Open **CoreX → Releases** again. It says where the installation stands and goes on from there:
before the folders moved it can be gone on with or given up; cut off while they were moving, it
is finished or every folder is put back, and the screen says which; after they moved, it
finishes the database step. None of that needs the recovery link.

An installation that stopped as the release was being put in place, before its first folder
moved, can be given up too: the screen says no folder moved. Giving it up lets visitors in, and
removes its recovery plugin, the unpacked release and the table copies it made. The
release kept from the installation before is still the one to go back to, unless the stopped
step had already set it aside: then **The release before** is no longer shown. Once a folder
has moved, **Give it up** is not offered: go on with it, which finishes the move or puts every
folder back, or go back to the release before.

If the screen finds what an installation left and no installation in progress, it shows **Left
from an installation** with each thing and where it is on the server:

| It names | What it is |
|---|---|
| A release unpacked for an installation that is no longer in progress | `wp-content/corex-releases/staging/` holds folders |
| The release recovery plugin | `wp-content/mu-plugins/corex-release-recovery.php` asks for the key of no installation and of no kept release: it has nothing to put back |
| Folders kept as a previous release, with no journal | `wp-content/corex-releases/previous/` holds folders and no `journal.json`: they cannot be gone back to |

**Remove them** removes all of it and changes nothing of the release the site runs. The folders
are set aside to `discarded/` and deleted a few seconds at a time each time Releases is opened.
If the site cannot remove them, delete them in the host's file manager. A `.maintenance` file
left in the folder WordPress is in is not listed: WordPress stops honouring it ten minutes after
the time written in it, and the host's file manager removes it.

If the folders have moved and nobody comes back, WordPress stops holding visitors off by itself
after ten minutes. The site then serves the new release, and CoreX brings the database up to
date on the first admin page or scheduled run, as it does for a release unpacked by hand. The
screen still records the installation as finished only when somebody opens it.

## What is replaced, and what is not

**Replaced whole:** every folder the package says it holds: each CoreX plugin and add-on, the
site's own plugin and theme, the CoreX theme, any plugin named with `--with-plugin`,
`wp-content/vendor` and `wp-content/packages`. Whole means a folder is moved out and the new one
moved in. A file the release before had and this one does not is not left behind, and a file
somebody changed or added on the host inside one of those folders is replaced.

**Moved out with the previous release:** a folder the running release held and this package does
not. A plugin that goes this way is switched off by WordPress, which finds it missing. The
confirmation names each such folder before you type the version.

**Left exactly as it is:** `wp-config.php`, `.htaccess`, `wp-content/uploads`, the database's
content, WordPress itself, translations, and any plugin or theme the package does not name,
another site's folder in the same web root among them. When the package was built with another
WordPress than the site runs, the statement says so; updating WordPress is not a release's job.

## The database

A release's schema changes are a step of its installation. The request that makes them is
answered by the release just installed, while visitors are still held off, and what it did is
listed: each part of CoreX's database with the version it was at and the version it is at.

It is the same work CoreX does by itself on the first admin page or scheduled run after its files
change, and the same `wp corex migrate` does. During an installation only the installation can
do it. Afterwards the automatic check finds nothing to do.

If a change fails, the screen names it. The release is not recorded as installed, and visitors
stay held off (for at most WordPress's ten minutes). Open Releases: it says why the database
step stopped, and offers going back.

Before any of this, each of CoreX's tables is copied as it was, to a table named
`<table>_cxrel_<year month day hour minute>`. The copies are for a person to read from with the
host's database tool. Nothing puts one back. They are removed fourteen days later, the next time
somebody opens Releases, and an installation removes the copies of the one before it. Not copied:
anything WordPress keeps as posts or options (submissions, forms, settings), and, on a network,
the tables of any site but the main one.

## The way back

### From the screen

**CoreX → Releases → The release before** says which release is kept and which replaced it.
**Go back to CoreX 1.2.3…**, type the version, confirm. The previous release's folders are put
back at once.

### When the admin does not load

Open the recovery link in a browser. It needs neither the admin nor CoreX: it is a small
must-use plugin written before the folders moved. It puts every folder back, takes the site out
of maintenance and says what it did in plain text. The next time somebody opens Releases, the
screen finds the return, records it and removes what the newer release left.

Keep the link to yourself. Anybody who has it can put the previous release back. It stops
working when another release is installed, or once the site has gone back.

### What going back does not undo

**No change to the database is reversed.** A table or column the newer release added stays. The
previous release runs on the tables as they are now, and records its own schema version again
on its first admin page, which adds what is missing and removes nothing. A column whose type the
newer release changed may be changed back to the older type by that check.

A release whose database changes the release before cannot run on has to say so in its own
notes. Read a release's **Client impact** in `CHANGELOG.md` before installing it, as for any
update.

Going back removes the newer release from the site. To install it again, send its package again.

## Refusals

A package the site will not install is refused before anything changes, in words, written in the
record, and removed from the site. Keep your own copy.

| The site says | Why |
|---|---|
| Not a zip that can be opened | The upload was cut short, or the file is not a zip |
| Holds a folder that holds the package | `dist/` itself was zipped. Build with `--zip` |
| No `corex-release.json` at its top | Not a CoreX package |
| Built before packages could be installed from the admin | Built by CoreX 0.43.5 or earlier. Build it again |
| Built for another client | The package's client is not this site's. Both are named |
| Needs a newer PHP or WordPress | The requirement and what the host has are named |
| Says it holds a folder, and does not | The zip is incomplete |
| Holds a file whose path leaves the package, or one a release never holds | Not unpacked |
| What was unpacked is not what the package says it holds | A file changed after the build, or in transit. Nothing on the site was changed |

A package the host cannot install today (see the table further up) is not removed for it.

## What it leaves on the host

| Where | What | Until |
|---|---|---|
| `wp-content/corex-releases/incoming/` | Packages received and not yet installed | Installed, refused, or seven days old |
| `wp-content/corex-releases/previous/` | The folders the last installation replaced | The next installation, or going back |
| `wp-content/corex-releases/discarded/` | The release kept before, set aside by the next installation | Deleted a few seconds at a time each time Releases is opened |
| `wp-content/corex-releases/log.json` | The record: the last fifty events | Kept |
| `wp-content/mu-plugins/corex-release-recovery.php` | The way back that needs no admin | The next installation, or going back |
| `.maintenance` in the folder WordPress is in | WordPress's maintenance answer | The end of the database step |
| Tables named `…_cxrel_<minute>` | The copies of CoreX's tables | Fourteen days |

CoreX writes an `.htaccess`, an `index.php` and a `web.config` in `corex-releases/` that refuse
a web server's requests for what is in it, as it does in its other private folders. nginx reads
none of the three: deny the folder in the server's own configuration there. If the host's backup
plugin copies all of `wp-content`, exclude `corex-releases/`: it is a second copy of the
release's code.

## The record

**What happened here**, at the bottom of the screen, lists each installation, refusal, return,
and installation that did not complete or was given up: what, who, when. It shows the newest
twenty; `log.json` keeps fifty. Installations, returns, and installations that did not complete
or were given up are in CoreX's activity log too. A return made through the recovery link has
nobody against it: the link asks for a key, not a name.

## See also

- [Releases from your machine, in one command](./releases-from-your-machine.md): the same installation
  from `npm run release:push`, with no browser.
- [The first installation, by hand](./first-installation-by-hand.md) — once per site, and how a
  site that has been updated by unpacking zips moves to the screen.
- [Shared-host dist artifact](./shared-host-dist.md) — the builder, `--zip` and `--with-plugin`.
- [Updating CoreX in a client site](./updating-a-client-site.md) — taking a release into the
  repository, which comes before building its package.
