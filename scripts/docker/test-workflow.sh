#!/usr/bin/env bash
# Static checks on the publish workflow, and the tag rules. See
# docs/docker-build.md, "Publishing and supply chain".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

WORKFLOW="$REPO_ROOT/.github/workflows/docker-publish.yml"
TAGS="$REPO_ROOT/scripts/docker/compute-tags.sh"

echo "workflow: tag rules"
# tags <tag> <variant> [existing git tags...]: the tags that would be published.
tags() {
  local tag="$1" variant="$2"
  shift 2
  if [[ $# -gt 0 ]]; then
    printf '%s\n' "$@" | "$TAGS" --existing - "$tag" "$variant" 2>&1 | paste -sd' ' -
  else
    "$TAGS" "$tag" "$variant" 2>&1 | paste -sd' ' -
  fi
}
kind() { "$TAGS" --kind "$1" 2>&1; }

# A release moves 4.0, 4 and latest; the slim variant suffixes everything.
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5 full)" "release: exact, minor, major, latest"
assert_eq "4.0.5-slim 4.0-slim 4-slim latest-slim" "$(tags 4.0.5 slim)" "release, slim variant"
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5 full 4.0.3 4.0.4 4.0.5)" "release newer than every other tag"
# Pre-releases never move anything.
assert_eq "4.0.0-rc" "$(tags 4.0.0-rc full)" "release candidate: exact tag only"
assert_eq "4.0.0-beta37-slim" "$(tags 4.0.0-beta37 slim)" "beta, slim: exact tag only"
# An image revision (-rN) is a new image of the same Mukurtu release.
assert_eq "4.0.5-r1 4.0 4 latest" "$(tags 4.0.5-r1 full 4.0.5 4.0.5-r1)" "revision of the newest release moves the moving tags"
assert_eq "4.0.5-r1-slim 4.0-slim 4-slim latest-slim" "$(tags 4.0.5-r1 slim 4.0.5 4.0.5-r1)" "revision, slim variant"
assert_eq "4.0.5-r2 4.0 4 latest" "$(tags 4.0.5-r2 full 4.0.5 4.0.5-r1 4.0.5-r2)" "a second revision is newer than the first"
assert_eq "4.0.5-r1" "$(tags 4.0.5-r1 full 4.0.5 4.0.5-r2 4.0.5-r1)" "an older revision published late moves nothing"
assert_eq "4.0.5-r1" "$(tags 4.0.5-r1 full 4.0.5 4.0.5-r1 4.0.6)" "a revision of an older release does not move tags back"
assert_eq "4.0.4-r1" "$(tags 4.0.4-r1 full 4.0.4 4.0.5 4.0.4-r1)" "backport: 4.0, 4 and latest stay on 4.0.5"
assert_eq "3.9.9-r1 3.9 3" "$(tags 3.9.9-r1 full 3.9.9 4.0.5 3.9.9-r1)" "older line: its own 3.9 and 3 move, latest does not"
assert_eq "4.1.0 4.1 4 latest" "$(tags 4.1.0 full 4.0.5 4.1.0)" "a new minor moves 4 and latest and starts 4.1"
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5 full 4.0.5 4.2.0-rc 4.x-dev v9.9.9 junk)" "pre-releases and junk tags are ignored"
assert_eq "4.0.5" "$(tags 4.0.5 full 4.0.5 4.0.5-r1)" "the plain tag is not re-pushed once a revision exists"
# Kinds.
assert_eq "stable" "$(kind 4.0.5)" "kind: stable"
assert_eq "revision" "$(kind 4.0.5-r1)" "kind: revision"
assert_eq "revision" "$(kind 4.0.5-r12)" "kind: revision with two digits"
assert_eq "prerelease" "$(kind 4.0.0-rc)" "kind: release candidate"
assert_eq "prerelease" "$(kind 4.0.0-rc1)" "kind: rc1 is a pre-release, not revision 'c1'"
assert_eq "prerelease" "$(kind 4.0.0-beta37)" "kind: beta"
for bad in "4.x-dev full" "v4.0.5 full" "4.0 full" "4.0.5 medium" "4.0.5-r0 full" "4.0.5-r01 full" "4.0.5 full extra" ""; do
  # shellcheck disable=SC2086
  if "$TAGS" $bad >/dev/null 2>&1; then fail "rejects: '$bad'"; else pass "rejects: '$bad'"; fi
done

echo "workflow: file"
if [[ ! -f "$WORKFLOW" ]]; then
  fail "docker-publish.yml exists"; finish; exit 1
fi
pass "docker-publish.yml exists"

if docker run --rm -v "$REPO_ROOT:/repo:ro" -w /repo rhysd/actionlint:1.7.7 .github/workflows/docker-publish.yml >/tmp/actionlint.out 2>&1; then
  pass "actionlint finds nothing"
else
  fail "actionlint finds nothing"; cat /tmp/actionlint.out
fi

python3 - "$WORKFLOW" "$REPO_ROOT/.github/dependabot.yml" <<'PY' && pass "workflow structure" || fail "workflow structure"
import re, sys, yaml

wf = yaml.safe_load(open(sys.argv[1]))
dep = yaml.safe_load(open(sys.argv[2]))
text = open(sys.argv[1]).read()
errors = []

on = wf.get(True) or wf.get("on")  # YAML 1.1 reads a bare `on` as True
for trigger in ("release", "workflow_dispatch"):
    if trigger not in on:
        errors.append(f"missing trigger: {trigger}")
# A published tag is a fixed artifact. Nothing rebuilds and re-pushes existing
# tags on a timer; fixes arrive as new releases.
if "schedule" in on:
    errors.append("no schedule trigger: published tags must not be rebuilt in place")
if on.get("release", {}).get("types") != ["published"]:
    errors.append("release trigger must be types: [published]")

# No literal owner anywhere: the image name comes from github.repository_owner.
for owner in ("mukurtucms", "cbenning"):
    if owner in text.lower():
        errors.append(f"literal owner '{owner}' in the workflow")
if "github.repository_owner" not in text:
    errors.append("image name must use github.repository_owner")

# Untrusted event data must reach scripts through env:, never be interpolated
# into a run: block (script injection).
jobs = wf["jobs"]
for jname, job in jobs.items():
    for step in job.get("steps", []):
        run = step.get("run", "")
        for expr in re.findall(r"\$\{\{(.*?)\}\}", run):
            if re.search(r"github\.event\.|inputs\.|github\.head_ref", expr):
                errors.append(f"{jname}: event data interpolated into run: {expr.strip()}")

# A release that GitHub marks as a pre-release must carry a pre-release suffix in
# its tag (4.0.0-rc), because the tags are derived from the version alone.
# Otherwise 4.0.5 would take `latest` although it was published as a pre-release.
if "marked as a pre-release" not in text:
    errors.append("resolve must refuse a GitHub pre-release whose tag has no pre-release suffix")

# Release tags are classified by the tested script, not by regexes of their own,
# and moving tags are judged against the tags that already exist.
if "compute-tags.sh --kind" not in text:
    errors.append("resolve must classify the tag with compute-tags.sh --kind")
if "compute-tags.sh --existing -" not in text:
    errors.append("publish must pass the existing tags to compute-tags.sh --existing -")

# Both image variants name their tool choice explicitly.
publish = jobs["publish"]
rows = publish["strategy"]["matrix"]["include"]
variants = {}
for row in rows:
    for key in ("variant", "with_ffmpeg", "with_poppler"):
        if key not in row:
            errors.append(f"publish matrix row lacks {key}: {row}")
    variants[row.get("variant")] = (row.get("with_ffmpeg"), row.get("with_poppler"))
if variants != {"full": ("true", "true"), "slim": ("false", "false")}:
    errors.append(f"matrix variants wrong: {variants}")

# Nothing is pushed unless the tests and the vulnerability scan passed.
needs = publish.get("needs")
needs = [needs] if isinstance(needs, str) else needs
if "test" not in needs:
    errors.append("publish must need the test job")
test_text = yaml.dump(jobs["test"])
if "trivy" not in test_text.lower():
    errors.append("the test job must run Trivy")
for flag in ("HIGH,CRITICAL", "ignore-unfixed", "exit-code"):
    if flag not in test_text:
        errors.append(f"trivy step lacks {flag}")
if "scripts/docker/test-" not in test_text:
    errors.append("the test job must run scripts/docker/test-*.sh")

# Permissions: least privilege, write only where it is needed.
if jobs["test"].get("permissions", {}).get("packages") == "write":
    errors.append("the test job must not have packages: write")
if publish.get("permissions", {}).get("packages") != "write":
    errors.append("publish needs packages: write")

# Multi-arch, provenance, SBOM, cache.
pub_text = yaml.dump(publish)
for needle in ("linux/amd64,linux/arm64", "provenance", "sbom", "cache-from", "cache-to"):
    if needle not in pub_text:
        errors.append(f"publish lacks {needle}")

# Dependabot watches the Dockerfile and the actions.
ecosystems = {u["package-ecosystem"] for u in dep["updates"]}
for eco in ("docker", "github-actions"):
    if eco not in ecosystems:
        errors.append(f"dependabot.yml lacks {eco}")

if errors:
    print("\n".join(errors), file=sys.stderr)
    sys.exit(1)
PY

finish
