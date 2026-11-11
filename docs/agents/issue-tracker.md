# Issue tracker: GitHub

Issues for this repository live in GitHub Issues on `MustafaShaaban/corex`. Use the `gh` CLI for all of them.

**Specs do not live here.** A feature's spec is `specs/NNN-name/spec.md` (constitution, Principle X), and
the plan of what comes next is `ROADMAP.md`. An issue is for a bug, a lead, a client report or a question.
When a skill would publish a spec or a set of tickets to the tracker, write the spec and its slices in
`specs/` instead.

**Security findings never go in an issue.** The repository is public. Follow `SECURITY.md`.

## Conventions

- **Create an issue**: `gh issue create --title "..." --body "..."`. Use a heredoc for multi-line bodies.
- **Read an issue**: `gh issue view <number> --json number,title,body,labels,comments`.
- **List issues**: `gh issue list --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'` with appropriate `--label` and `--state` filters.
- **Comment on an issue**: `gh issue comment <number> --body "..."`
- **Apply / remove labels**: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`
- **Close**: `gh issue close <number> --comment "..."`

`gh` infers the repository when it runs inside the clone.

## Pull requests as a triage surface

**PRs as a request surface: no.** _(Set to `yes` if this repo treats external PRs as feature requests; `/triage` reads this flag.)_

GitHub shares one number space across issues and PRs, so a bare `#42` may be either: resolve with
`gh pr view 42` and fall back to `gh issue view 42`. In CoreX's own files a bare `#NNN` next to
"DECISIONS" is a decision number, not an issue.

## When a skill says "publish to the issue tracker"

Create a GitHub issue, unless what it would publish is a spec or a plan (see above).

## When a skill says "fetch the relevant ticket"

Read it as in **Read an issue** above. When the work comes from a spec, the spec and its slice are the
ticket.
