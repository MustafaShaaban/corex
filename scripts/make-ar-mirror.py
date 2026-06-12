"""Generate docs/ar/ as a file-for-file placeholder mirror of docs/en/ (spec 028, phase D10).

Each Arabic page keeps the English front-matter and a 'translation pending' placeholder that
links back to the English source. Underscore-prefixed templates are tooling, not pages, so
they are skipped. Re-runnable: regenerate after adding English pages.
"""
import os
import re
import io

EN_ROOT = "docs/en"
AR_ROOT = "docs/ar"
SEP = os.sep


def main() -> None:
    count = 0
    for dirpath, _dirs, files in os.walk(EN_ROOT):
        for name in files:
            if not name.endswith(".md") or name.startswith("_"):
                continue
            src = os.path.join(dirpath, name)
            rel = os.path.relpath(src, EN_ROOT)
            dst = os.path.join(AR_ROOT, rel)
            os.makedirs(os.path.dirname(dst), exist_ok=True)

            text = io.open(src, encoding="utf-8").read()
            match = re.match(r"^---\n(.*?)\n---\n", text, re.S)
            front_matter = match.group(1) if match else "title: \ndescription: "

            back = os.path.relpath(src, os.path.dirname(dst)).replace(SEP, "/")
            rel_url = rel.replace(SEP, "/")
            body = (
                "---\n" + front_matter + "\n---\n\n"
                "> **TODO: translation pending.** This Arabic page is a placeholder mirroring the English\n"
                "> source. Translate the prose only — code identifiers, commands, env vars, hook names, CLI\n"
                "> flags, and file paths stay in English (see\n"
                "> [`_translation-memory.md`](../../_translation-memory.md)).\n\n"
                "> English source: [`en/" + rel_url + "`](" + back + ")\n"
            )
            io.open(dst, "w", encoding="utf-8", newline="\n").write(body)
            count += 1
    print("mirrored " + str(count) + " pages into " + AR_ROOT + "/")


if __name__ == "__main__":
    main()
