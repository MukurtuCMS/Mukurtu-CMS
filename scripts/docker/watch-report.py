#!/usr/bin/env python3
"""Turn the advisory watch's scan results into the body of an issue.

    watch-report.py <composer-audit.json> <trivy.json> <image>

Prints Markdown when there is anything to report, and nothing when both scans are
clean. The output lists findings in a fixed order, so the watch can compare it with the
open issue and only update that issue when the findings change.
"""
import json
import os
import sys


def cell(text):
    """Keeps a value from breaking out of its table cell."""
    return " ".join(str(text or "").split()).replace("|", "\\|")


def composer_rows(path):
    with open(path) as handle:
        advisories = json.load(handle).get("advisories") or {}
    # Composer gives a list per package, or an object once it has filtered that list.
    for package, found in advisories.items():
        for advisory in found.values() if isinstance(found, dict) else found:
            identifier = advisory.get("advisoryId", "")
            link = advisory.get("link")
            yield (package, f"[{identifier}]({link})" if link else identifier, advisory.get("title"))


def trivy_rows(path):
    with open(path) as handle:
        results = json.load(handle).get("Results") or []
    for result in results:
        for found in result.get("Vulnerabilities") or []:
            identifier = found.get("VulnerabilityID", "")
            link = found.get("PrimaryURL")
            yield (
                found.get("PkgName"),
                f"[{identifier}]({link})" if link else identifier,
                found.get("Severity"),
                found.get("InstalledVersion"),
                found.get("FixedVersion"),
            )


def table(headers, rows):
    lines = ["| " + " | ".join(headers) + " |", "|" + " --- |" * len(headers)]
    lines += ["| " + " | ".join(cell(value) for value in row) + " |" for row in rows]
    return lines


def main():
    audit_path, trivy_path, image = sys.argv[1:4]
    composer = sorted(set(composer_rows(audit_path)), key=str)
    trivy = sorted(set(trivy_rows(trivy_path)), key=str)
    if not composer and not trivy:
        return

    repository = os.environ.get("GITHUB_REPOSITORY", "MukurtuCMS/Mukurtu-CMS")
    steps = f"https://github.com/{repository}/blob/main/docs/releasing.md#releasing-an-image-revision"
    lines = [
        f"The daily watch found security advisories that affect `{image}`. Docker sites can't",
        "patch themselves, so a fix reaches them only in a new image.",
        "",
        f"Decide whether this needs an [image revision]({steps}).",
        "A revision updates the Composer packages below automatically, and takes base image",
        "fixes from the `FROM` lines in the `Dockerfile`. Close this issue once it's published,",
        "or if no action is needed. A Composer advisory may not have a fix released yet.",
    ]
    if composer:
        lines += ["", "## Composer packages", ""]
        lines += table(["Package", "Advisory", "Title"], composer)
    if trivy:
        lines += ["", "## Image scan (fixable, high and critical)", ""]
        lines += table(["Package", "Advisory", "Severity", "Installed", "Fixed in"], trivy)
    print("\n".join(lines))


if __name__ == "__main__":
    main()
