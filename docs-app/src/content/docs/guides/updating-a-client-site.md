---
title: Update CoreX in a client site
description: Create a client repository from a CoreX release, and take each later release without losing any of the client's own work.
---

A client site is built in its own repository: a copy of the CoreX framework with the client's site
beside it. It takes each new CoreX release by **merging** it. Examples use the neutral **Acme**
placeholder and the release tag `v1.2.3`.

## Who owns what

| Owner | Paths |
|---|---|
| **Client** | `sites/**` and `.github/workflows/site-*.yml` |
| **Framework** | Everything else, the repository root included |

The client-owned patterns are listed in `.github/repository-ownership.json`; the framework is
everything not on that list.

:::tip[The rule that keeps an update conflict-free]
Client work never edits a framework-owned path. No framework release ships a file under `sites/`,
so a merge cannot overwrite your site — the only way to get a conflict is to have changed something
the framework also changes. That includes the root `README.md`, `PROGRESS.md`, `DECISIONS.md` and
`CHANGELOG.md`: the client's own are in `sites/acme/`.
:::

## Create the client repository

```bash
git clone --branch v1.2.3 https://github.com/MustafaShaaban/corex.git acme
cd acme
git switch -c main
git remote rename origin upstream
git remote set-url --push upstream DISABLED_DO_NOT_PUSH_TO_COREX
git remote add origin git@github.com:your-account/acme.git
git rm -q .github/dependabot.yml .github/CODEOWNERS
git commit -q -m "Remove the framework's Dependabot and CODEOWNERS files"
git push -u origin main
```

`upstream` is now the framework, fetch-only: a push to it fails instead of reaching the framework.

The two files removed before the first push are the only framework files a client repository may
delete (`clientMayRemove` in `.github/repository-ownership.json`). Left in place, Dependabot opens
weekly pull requests against the framework's lockfiles, which a client must never merge, and
CODEOWNERS asks the framework's reviewer to review the client's work. The check reports each as
`REMOVED` and passes; editing one is still drift.

In the new repository the framework's CI runs on every push and pull request. The scheduled runs,
the documentation deploy and the dependency advisory check do not run there, and CodeQL runs only
when the repository is public.

Install it, create the local WordPress ([WAMP / Apache + WP-CLI](/getting-started/wamp-apache/)),
then generate the site from the repository root:

```bash
composer install && npm ci && npm run build
wp corex make:site Acme --dir=sites/acme --path=wp
```

`--dir` is where the site goes; `--path` is WP-CLI's own option for the WordPress install. Because
the site is at `sites/acme`, the command also writes:

| File | What it is |
|---|---|
| `sites/acme/corex-baseline.json` | The framework release and commit this site is on. |
| `sites/acme/UPDATING-COREX.md` | The update procedure as a checklist, with your paths. |
| `.github/workflows/site-acme.yml` | The client's own CI. No framework release ships a file with this name. |

Confirm the starting point and commit:

```bash
npm run verify:framework
git add sites .github/workflows/site-acme.yml
git commit -m "Add the Acme site"
```

```text
framework	PASS	0 drifted	0 accepted exceptions	baseline v1.2.3 <commit>
```

## Check before you push

```bash
npm run verify:framework
```

It compares every framework-owned path with the commit recorded in `corex-baseline.json`,
uncommitted changes and untracked files included. Anything that differs is listed:

```text
DRIFT	README.md
framework	FAIL	1 drifted	0 accepted exceptions	baseline v1.2.3 <commit>
```

Undo the change, or move what you needed into `sites/acme/`. The generated workflow runs the same
check on every pull request, plus a clean install and build of the client's packages and the
client's own tests.

## Take a release

1. **Start clean, on a new branch**, and confirm `npm run verify:framework` passes.
2. **Read the _Client impact_ section** of `CHANGELOG.md` for every release between the one you are
   on and the one you are taking. A clean merge does not mean nothing your site depends on changed.
3. **Merge it.**

   ```bash
   git fetch upstream --tags
   git merge v1.2.4
   ```

4. **Install and build** the framework, then the client's packages.

   ```bash
   composer install && npm ci && npm run build
   ```

   In `sites/acme/acme-site` and `sites/acme/acme-theme`, wherever there is a `package.json`, run
   `npm ci` (or `npm install`) and `npm run build`.

5. **Apply any database change.**

   ```bash
   wp corex migrate --path=wp
   ```

6. **Record the new baseline, then check against it.**

   ```bash
   npm run verify:framework -- --record v1.2.4
   npm run verify:framework
   ```

   ```text
   RECORDED	sites/acme/corex-baseline.json	v1.2.4	<commit>
   framework	PASS	0 drifted	0 accepted exceptions	baseline v1.2.4 <commit>
   ```

   Record first. Before that the check still compares with the old release and reports the update
   itself as drift.

7. **Run the framework's suites and the client's**, compare the public pages with how they were,
   and open a pull request. `corex-baseline.json` is the only file under `sites/` that changes.

## When the merge conflicts

A conflict in a framework-owned path means it was edited in this repository. Take the framework's
version — during a merge, `--theirs` is the release being merged:

```bash
git checkout --theirs -- README.md
git add README.md
git commit
```

If a release changes `.github/dependabot.yml` or `.github/CODEOWNERS` and this repository deleted
it, git reports a modify/delete conflict. Keep it deleted: `git rm` the file and commit.

## When a framework defect blocks the client

Report it in the framework's repository and take the fix by update. If the client cannot wait,
patch the file and record it under `exceptions` in `corex-baseline.json`, with the `path`, a
`reason` and the `upstream` issue. The check then reports it as an `EXCEPTION` instead of failing,
and fails it as `STALE` once a release contains the fix — at which point delete the exception.

## See also

- [Build a client site](/guides/client-site/) — what `make:site` generates.
- [Updates & distribution](/guides/updates/) — the in-admin updater, for a deployed WordPress with
  no repository behind it.
- [Deploy & distribute](/guides/deployment/) — building what is deployed.
