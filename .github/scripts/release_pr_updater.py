#!/usr/bin/env python3
"""
Release PR updater.

Called by .github/workflows/release-pr-updater.yml after a PR is merged to develop.
Updates version files + readme.txt changelog's pending block in place, then emits
the Release PR title and body to GITHUB_OUTPUT.

Inputs (env):
  PR_TITLE         Merged PR title (used as the new changelog bullet)
  PR_NUMBER        Merged PR number
  PR_LABELS_JSON   JSON array of label names on the merged PR
  REPO_ROOT        Plugin root (contains hezarfen-for-woocommerce.php, readme.txt)

Outputs (GITHUB_OUTPUT):
  version          Computed pending version (X.Y.Z)
  bump             Effective bump (major|minor|patch)
  pr_body_file     Path to a file containing the rendered Release PR body
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from datetime import date, timezone, datetime
from pathlib import Path


BUMP_ORDER = {"patch": 1, "minor": 2, "major": 3}


def run(cmd: list[str], cwd: Path) -> str:
    return subprocess.check_output(cmd, cwd=cwd, text=True).strip()


def latest_semver_tag(repo: Path) -> str:
    tags = run(["git", "tag", "--sort=-v:refname"], repo).splitlines()
    for t in tags:
        if re.fullmatch(r"\d+\.\d+\.\d+", t):
            return t
    raise SystemExit("No semver tag found in repo; cannot compute bump base.")


def detect_bump(pr_title: str, labels: list[str]) -> str:
    if "bump:major" in labels:
        return "major"
    if "bump:minor" in labels:
        return "minor"
    if "bump:patch" in labels:
        return "patch"
    if re.match(r"feat(\([^)]*\))?:", pr_title):
        return "minor"
    return "patch"


def max_bump(a: str, b: str) -> str:
    return a if BUMP_ORDER[a] >= BUMP_ORDER[b] else b


def apply_bump(version: str, bump: str) -> str:
    major, minor, patch = map(int, version.split("."))
    if bump == "major":
        return f"{major + 1}.0.0"
    if bump == "minor":
        return f"{major}.{minor + 1}.0"
    return f"{major}.{minor}.{patch + 1}"


def infer_bump(from_version: str, to_version: str) -> str:
    fm, fn, _ = map(int, from_version.split("."))
    tm, tn, _ = map(int, to_version.split("."))
    if tm > fm:
        return "major"
    if tn > fn:
        return "minor"
    return "patch"


def is_higher(a: str, b: str) -> bool:
    return tuple(map(int, a.split("."))) > tuple(map(int, b.split(".")))


BLOCK_HEADER_RE = re.compile(r"(?m)^= (\d+\.\d+\.\d+) - (\S+) =\s*$")
CHANGELOG_HEADER_RE = re.compile(r"(?m)^== Changelog ==\s*$")


def update_readme_changelog(
    readme_text: str,
    new_version: str,
    new_bullet: str,
    today: str,
) -> tuple[str, list[str]]:
    """
    Update the pending changelog block (or prepend one) and return
    (updated_readme_text, bullets_of_pending_block).
    """
    m = CHANGELOG_HEADER_RE.search(readme_text)
    if not m:
        raise SystemExit("readme.txt is missing '== Changelog ==' header.")

    before = readme_text[: m.end()]
    rest = readme_text[m.end():]
    leading_blank = ""
    while rest.startswith("\n"):
        leading_blank += "\n"
        rest = rest[1:]

    headers = list(BLOCK_HEADER_RE.finditer(rest))
    if not headers:
        raise SystemExit("readme.txt changelog has no version blocks.")

    first_header = headers[0]
    first_version = first_header.group(1)

    # Split first block body out (everything between first header and next header,
    # or end of changelog section if only one block).
    first_body_start = first_header.end()
    first_body_end = headers[1].start() if len(headers) > 1 else len(rest)
    first_body = rest[first_body_start:first_body_end]
    tail = rest[first_body_end:]

    bullet_line = f"* {new_bullet.strip()}"

    if first_version == new_version:
        # Pending block with same version: append bullet at end of bullet list.
        new_body = append_bullet(first_body, bullet_line)
        new_header_line = f"= {new_version} - {today} =\n"
        rebuilt = before + leading_blank + new_header_line + new_body + tail
        bullets = extract_bullets(new_body)
        return rebuilt, bullets

    if is_higher(first_version, latest_tag_cache["v"]):
        # Existing pending block but with different (lower) version — we bumped higher.
        # Rewrite header to new_version, keep bullets, append new bullet, refresh date.
        new_body = append_bullet(first_body, bullet_line)
        new_header_line = f"= {new_version} - {today} =\n"
        rebuilt = before + leading_blank + new_header_line + new_body + tail
        bullets = extract_bullets(new_body)
        return rebuilt, bullets

    # No pending block: prepend a fresh one.
    fresh_block = f"= {new_version} - {today} =\n{bullet_line}\n\n"
    rebuilt = before + leading_blank + fresh_block + rest
    return rebuilt, [bullet_line[2:].strip()]


def append_bullet(body: str, bullet_line: str) -> str:
    """
    Append a new bullet at the end of the bullet list in `body` (which sits
    between two block headers or at end of changelog). Preserves a trailing
    blank line separator before the next block.
    """
    # Split off trailing blank lines that separate this block from the next.
    stripped = body.rstrip("\n")
    trailing_blanks = body[len(stripped):]
    if not trailing_blanks:
        trailing_blanks = "\n\n"
    return stripped + "\n" + bullet_line + "\n" + trailing_blanks.lstrip("\n")


def extract_bullets(body: str) -> list[str]:
    return [
        line[2:].strip()
        for line in body.splitlines()
        if line.startswith("* ") and line[2:].strip()
    ]


def update_plugin_file(text: str, new_version: str) -> str:
    text, n1 = re.subn(
        r"(?m)(^[ \t]*\*[ \t]*Version:[ \t]*)\S+",
        lambda m: f"{m.group(1)}{new_version}",
        text,
        count=1,
    )
    if n1 != 1:
        raise SystemExit("Could not find ' * Version:' line in plugin main file.")
    text, n2 = re.subn(
        r"(define\([ \t]*'WC_HEZARFEN_VERSION'[ \t]*,[ \t]*')[^']+(')",
        lambda m: f"{m.group(1)}{new_version}{m.group(2)}",
        text,
        count=1,
    )
    if n2 != 1:
        raise SystemExit("Could not find WC_HEZARFEN_VERSION define.")
    return text


def update_stable_tag(text: str, new_version: str) -> str:
    text, n = re.subn(
        r"(?m)(^Stable tag:[ \t]*)\S+",
        lambda m: f"{m.group(1)}{new_version}",
        text,
        count=1,
    )
    if n != 1:
        raise SystemExit("Could not find 'Stable tag:' line in readme.txt.")
    return text


def render_pr_body(new_version: str, bump: str, bullets: list[str], pr_number: int) -> str:
    bullet_block = "\n".join(f"* {b}" for b in bullets) if bullets else "* (boş)"
    return (
        f"## Release v{new_version}\n\n"
        f"Otomatik güncellenen release PR. **Bu body'yi elle düzenleme** — source of truth "
        f"`readme.txt`'nin en üstündeki pending changelog bloğu. Düzenleme yapmak için "
        f"`readme.txt`'yi edit edip develop'a push et; sonraki merge'de body yeniden mirror'lanır.\n\n"
        f"## Bump\n{bump}\n\n"
        f"## Changelog\n{bullet_block}\n\n"
        f"---\n"
        f"Son eklenen: #{pr_number}. Merge edildiğinde `auto-tag-release.yml` tag + GitHub Release + "
        f"WP.org deploy zincirini otomatik çalıştırır.\n"
    )


latest_tag_cache: dict[str, str] = {}


def main() -> None:
    repo = Path(os.environ["REPO_ROOT"]).resolve()
    pr_title = os.environ["PR_TITLE"].strip()
    pr_number = int(os.environ["PR_NUMBER"])
    labels = json.loads(os.environ.get("PR_LABELS_JSON", "[]"))

    latest_tag = latest_semver_tag(repo)
    latest_tag_cache["v"] = latest_tag

    new_bump = detect_bump(pr_title, labels)

    plugin_file = repo / "hezarfen-for-woocommerce.php"
    readme_file = repo / "readme.txt"
    plugin_text = plugin_file.read_text(encoding="utf-8")
    readme_text = readme_file.read_text(encoding="utf-8")

    # Peek at current pending block (if any) to know if an existing higher bump
    # is already in play, so we can escalate only upward.
    m = CHANGELOG_HEADER_RE.search(readme_text)
    if not m:
        raise SystemExit("readme.txt is missing '== Changelog ==' header.")
    rest = readme_text[m.end():].lstrip("\n")
    first_header = BLOCK_HEADER_RE.search(rest)
    if not first_header:
        raise SystemExit("readme.txt changelog has no version blocks.")
    first_version = first_header.group(1)

    if is_higher(first_version, latest_tag):
        existing_bump = infer_bump(latest_tag, first_version)
        effective_bump = max_bump(existing_bump, new_bump)
    else:
        effective_bump = new_bump

    new_version = apply_bump(latest_tag, effective_bump)
    today = datetime.now(timezone.utc).date().isoformat()

    new_readme, bullets = update_readme_changelog(
        readme_text, new_version, pr_title, today
    )
    new_readme = update_stable_tag(new_readme, new_version)
    new_plugin = update_plugin_file(plugin_text, new_version)

    plugin_file.write_text(new_plugin, encoding="utf-8")
    readme_file.write_text(new_readme, encoding="utf-8")

    body = render_pr_body(new_version, effective_bump, bullets, pr_number)
    body_file = Path(os.environ.get("RUNNER_TEMP", "/tmp")) / "release_pr_body.md"
    body_file.write_text(body, encoding="utf-8")

    gh_output = os.environ.get("GITHUB_OUTPUT")
    if gh_output:
        with open(gh_output, "a", encoding="utf-8") as f:
            f.write(f"version={new_version}\n")
            f.write(f"bump={effective_bump}\n")
            f.write(f"pr_body_file={body_file}\n")

    print(f"latest_tag={latest_tag}")
    print(f"detected_bump={new_bump}")
    print(f"effective_bump={effective_bump}")
    print(f"new_version={new_version}")
    print(f"bullets_count={len(bullets)}")


if __name__ == "__main__":
    main()
