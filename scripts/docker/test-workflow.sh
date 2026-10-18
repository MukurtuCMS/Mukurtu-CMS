#!/usr/bin/env bash
# Static checks on the publish workflow, and the tag rules. See
# docs/docker-build.md, "Publishing and supply chain".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

WORKFLOW="$REPO_ROOT/.github/workflows/docker-publish.yml"
TAGS="$REPO_ROOT/scripts/docker/compute-tags.sh"

echo "workflow: tag rules"
tags() { "$TAGS" "$@" 2>&1 | paste -sd' ' -; }
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5 full)" "release: exact, minor, major, latest"
assert_eq "4.0.5-slim 4.0-slim 4-slim latest-slim" "$(tags 4.0.5 slim)" "release, slim variant"
assert_eq "4.0.0-rc" "$(tags 4.0.0-rc full)" "release candidate: exact tag only"
assert_eq "4.0.0-beta37-slim" "$(tags 4.0.0-beta37 slim)" "beta, slim: exact tag only"
assert_eq "4.0.5 4.0 4 latest 4.0.5-20261017" "$(tags 4.0.5 full 20261017)" "scheduled rebuild adds an immutable dated tag"
assert_eq "4.0.5-slim 4.0-slim 4-slim latest-slim 4.0.5-20261017-slim" "$(tags 4.0.5 slim 20261017)" "scheduled rebuild, slim"
assert_eq "4.0.0-rc 4.0.0-rc-20261017" "$(tags 4.0.0-rc full 20261017)" "pre-release rebuild: still no moving tags"
for bad in "4.x-dev full" "v4.0.5 full" "4.0 full" "4.0.5 medium" "4.0.5 full 2026-10-17" ""; do
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
for trigger in ("release", "workflow_dispatch", "schedule"):
    if trigger not in on:
        errors.append(f"missing trigger: {trigger}")
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

# The scheduled rebuild is opt-in.
if "DOCKER_WEEKLY_REBUILD" not in text:
    errors.append("the scheduled rebuild must be guarded by vars.DOCKER_WEEKLY_REBUILD")

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
