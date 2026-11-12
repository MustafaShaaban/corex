---
title: Releases from your machine, in one command
description: Build a client's release and install it on its site with `npm run release:push`, signed in with an Application Password: the setup, the command, and what to do when it stops.
audience: ops
stability: draft
last_verified: null
---

# Releases from your machine, in one command

The [Releases screen](./releases-from-the-admin.md) installs a release from the browser: you upload
the package, read what it holds, type the version and keep the page open until it is done. This page
does the same thing with one command on your machine:

```bash
npm run release:push -- --client=acme
```

It builds the client's package (`npm run build:dist -- --client=acme --zip`), sends it to the site in
parts, installs it through the same routes the screen uses, with the screen's own client code, then
checks that the site answers and runs the new version. Nothing is installed that the screen would not
install, and every refusal of the screen is a refusal here too.

Examples use the neutral **Acme** placeholder, the site `https://acme.example` and the release `1.2.4`.

> **This has not been run against a shared host yet.** It is tested against real WordPress over HTTP
> on this machine: a whole installation, and the refusal when the site has not allowed it. Make the
> first run on a real host one of a release that changes nothing important, with the host's backup
> taken. `last_verified` stays `null` until somebody has.

## What it needs

- **A site already running a release that has this command's routes.** The release running on a site
  decides who may use its routes, and releases before this one accept only the Releases screen. So the
  first release that carries this command is installed [from the screen](./releases-from-the-admin.md);
  the ones after it can be installed with the command.
- **One line in the site's `wp-config.php`** that allows it (below). Without it every request of the
  command is refused.
- **HTTPS.** A site that is not a local one is reached only over `https://`.
- **An account that may install releases** (`corex_manage_releases`; on a network, a super
  administrator on the main site), and an **Application Password** of that account.
- **Node** on your machine, which building a package already needs.

## Set it up once per site

### 1. Allow it in `wp-config.php`

Add, above the line `/* That's all, stop editing! */`:

```php
define( 'COREX_ALLOW_RELEASE_PUSH', true );
```

Know what the line gives. Without it, a release is installed only by somebody signed in to the admin,
on the Releases screen, at that moment. With it, anyone holding an Application Password of an account
that may install releases can replace the site's code from anywhere. That is why it is a line in a file
only the server's owner can edit, and not a setting: a setting could be switched on by the same
password it lets in. A site that does not install releases this way leaves the line out.

A site that sets `DISALLOW_FILE_MODS` also needs `COREX_ALLOW_RELEASE_INSTALL`, exactly as for the
screen ([Releases from the admin](./releases-from-the-admin.md)). The two lines are separate: one allows
releases at all, the other allows them from a command.

### 2. Make an Application Password

1. Sign in to the site as the account that will install releases. An account kept for this alone is
   safer than your own: revoking its password then stops nothing else.
2. Open **Users → Profile**, find **Application Passwords**, name the new password `release push`, and
   choose **Add New Application Password**.
3. Copy the password WordPress shows. It is shown once.

The release history then says who installed each release and with which Application Password, so the
name you give it is the name you will read there.

### 3. Tell the command where the site is

Make a settings file **outside any git checkout**. The command refuses a settings file inside one, so it
cannot be committed by mistake. For a client called `acme` the command looks for this one by itself:

```text
~/.corex/release-push/acme.json        (Windows: %USERPROFILE%\.corex\release-push\acme.json)
```

```json
{
	"site": "https://acme.example",
	"user": "release-bot",
	"application_password": "abcd EFGH 1234 ijkl MNOP 5678"
}
```

In its place you can give `--settings=<file>`, name a file in `COREX_PUSH_SETTINGS`, or set all three of
`COREX_PUSH_SITE`, `COREX_PUSH_USER` and `COREX_PUSH_APP_PASSWORD`. The command looks in that order:
`--settings`, `COREX_PUSH_SETTINGS`, the three variables, then the client's file. The password is never
taken from an argument, because arguments stay in your shell's history, and the command never prints
it.

## Install a release

From the root of your repository:

```bash
npm run release:push -- --client=acme
```

A client whose build needs more takes the same options as `build:dist`:

```bash
npm run release:push -- --client=acme --with-plugin=fluent-smtp
```

A package you built already is sent as it is:

```bash
npm run release:push -- --package=corex-release-acme-1.2.4-20261025-120000.zip --client=acme
```

| Option | What it says |
|---|---|
| `--client=<name>` | The client to build, and the client the package must be for. |
| `--with-plugin=<folder>`, `--pdf-fonts=all` | Passed to `build:dist`. |
| `--package=<file>` | Send this package instead of building one. |
| `--settings=<file>` | The settings file to read. |
| `--accept-older` | The package is older than the release the site runs, and that is meant. |
| `--accept-downgrades` | Plugins or themes the package holds older versions of go back to those versions. |
| `--resume` | Go on with an installation a run of this command did not finish. |
| `--upload-only` | Stop once the package is on the site, for an agent to install it. |

A run that goes well prints, in order: the site and the release it runs, the package and its size, how
much of it the site holds, each step of the installation, and then:

```text
Installed: CoreX 1.2.4.
The site says it runs CoreX 1.2.4; its home page answers 200.
```

A home page that answers `503` is reported, not counted as a failure: it is what a site in maintenance
mode shows. A site that is not in maintenance mode and answers `503` should be looked at at once.

It ends with one of these exit codes, which a client's own script can act on:

| Code | Meaning |
|---|---|
| `0` | Installed, and the site runs it. |
| `1` | A usage or settings problem, or something the command must be told is meant (`--accept-older`, `--accept-downgrades`, `--client`). Nothing on the site changed. |
| `2` | The site refused, an installation stands on it, or it does not run what was installed. |
| `3` | The site could not be reached. |
| `4` | The build failed. Nothing was sent. |
| `5` | The command itself failed. It prints why; if it had started an installation, `--resume` goes on with it. |

## When it stops

The command says what happened in the site's own words, and what the way back is.

**The site refused before anything started.** The reasons you can meet first:

| Reason | What to do |
|---|---|
| `push_off` | Add `COREX_ALLOW_RELEASE_PUSH` to the site's `wp-config.php` (above). |
| `push_needs_https` | Use the site's `https://` address. |
| `not_allowed`, or WordPress's `rest_forbidden` | The account may not install releases: see "What it needs". |
| WordPress's `incorrect_password`, `invalid_username` or `rest_not_logged_in` | The user or the Application Password in your settings is wrong, or was revoked. |
| WordPress's `rest_forbidden`, with a password you know is right | WordPress may not see the request as HTTPS (a proxy or CDN ends HTTPS before PHP), and then it turns Application Passwords off. Ask the host to pass HTTPS on to PHP. |

The command then prints `Nothing on the site changed.`

**The site refused a step before the release was put in place.** No folder was moved. The site runs the
release it ran, and the installation is over and on record.

**It stopped after the release was put in place.** The command prints the way back:

```text
The release's folders may already be in place. Either:
  - run this command again with --resume, to go on with the installation; or
  - put the previous release back: CoreX > Releases on the site, "Go back", or the recovery link:
      https://acme.example/?corex_release_key=…&corex_release_recover=1
```

While the release is being put in place the site holds visitors off, and WordPress lets through only
the requests that carry the installation's key. The command keeps that key, and the recovery link, in a
file only you can read (`~/.corex/release-push/state/<host>.json`) from the moment it prepares an
installation until the installation ends, so `--resume` can carry it. It prints the recovery link only
when it is needed.

**It was stopped and run again.** A run that finds an installation standing on the site says where it
stands and stops. Run it with `--resume` to go on with it. If the key is not on this machine (the run
that started it was on another), WordPress lets every request in again by itself about ten minutes after
the release was put in place, and the command keeps asking for that long before it gives up. An
installation that stands at **stuck** cannot be gone on with: use the recovery link, or the Releases
screen.

## Take it away

- **Revoke the Application Password** (Users → Profile → Application Passwords). The command is turned
  away at once.
- **Remove the line** from `wp-config.php`. Every request of the command is refused with `push_off`, and
  the Releases screen goes on working as before.

## For a client site in a fork

Any client site in a fork of CoreX uses the same command with its own name. A client whose package needs
a step of its own before `build:dist` runs that step first, in its own script, and then this command,
for example in the client's `package.json`:

```json
{
	"scripts": {
		"deploy": "node sites/acme/deploy/prepare.mjs && npm run release:push -- --client=acme"
	}
}
```

The client keeps its settings file under `~/.corex/release-push/`, never in its repository.

## See also

- [Releases from the admin](./releases-from-the-admin.md): the screen, what is replaced, and the
  recovery link.
- [The first installation, by hand](./first-installation-by-hand.md): before CoreX runs on a site.
- Spec 123, User Story 4, and DECISIONS #512 and #513: why it is built this way.
