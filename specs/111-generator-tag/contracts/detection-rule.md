# Contract: the detection rule a scanner uses to recognise CoreX

What spec 111 asks the scanners' maintainers to add (FR-009, FR-010). Sending it is the owner's to
do or approve; nothing here is sent by the framework.

## Where it goes

| List | Status (checked 2026-10-19) | How to submit |
|---|---|---|
| `enthec/webappanalyzer` | Public, active (last push 2026-10-19), GPLv3. It continues Wappalyzer's open list, which went private in August 2023, in the same format. | A pull request adding the block below to `src/technologies/c.json` and an icon to `src/images/icons/CoreX.svg`. Its `CONTRIBUTING.md` asks for **ten or more links to websites that use the technology** in the pull request. |
| Wappalyzer | Its list is private. | The "suggest a technology" form on wappalyzer.com, with the same signals and links. |

Check both again before submitting: either may have changed.

## The rule

```json
"CoreX": {
  "cats": [18, 87],
  "description": "CoreX is an open-source, Laravel-inspired framework for building WordPress sites.",
  "icon": "CoreX.svg",
  "meta": {
    "generator": "^CoreX$"
  },
  "oss": true,
  "requires": ["WordPress"],
  "scriptSrc": [
    "/wp-content/plugins/corex-(?:blocks|config|core|forms)/"
  ],
  "website": "https://mustafashaaban.github.io/corex/"
}
```

- **`cats`**: 18 is "Web frameworks", 87 is "WordPress plugins" in the list's `categories.json`.
- **`meta.generator`**: the primary signal, the mark spec 111 adds. It matches the whole value, so
  `CoreX` and nothing else; there is no version to capture (FR-002).
- **`scriptSrc`**: the second signal, for sites on releases before the mark. It names the four
  framework plugins rather than any `corex-` folder, so an unrelated plugin whose name starts with
  `corex-` does not match.
- **`requires`**: the rule is only tried on a page already recognised as WordPress, as the list's
  WordPress plugins (WooCommerce, WP Rocket) do.
- **`icon`**: `plugins/corex-config/assets/corex-logo.svg`, copied into the pull request as
  `CoreX.svg` (the list names icons by file in `src/images/icons/`).

## Evidence the rule is right (SC-004)

Before submitting, test the rule against:

1. a CoreX site on a release that prints the mark: it reports CoreX;
2. a WordPress site without CoreX: it reports nothing.

The list's repository has a validator (`scripts/`) for the format; run it on the changed file.

## The ten sites

The pull request needs ten or more public sites running CoreX. Count them before submitting. With
fewer, the Wappalyzer form is the route, and the pull request waits until there are ten.
