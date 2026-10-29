#!/usr/bin/env bash
# Static checks, no build needed: the tag rules and the publish workflow's structure, and
# that the documentation agrees with the code (every setting documented with the default
# the scripts use, every part of the image explained in docs/docker-build.md).
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

WORKFLOW="$REPO_ROOT/.github/workflows/docker-publish.yml"
TAGS="$REPO_ROOT/scripts/docker/compute-tags.sh"

echo "workflow: tag rules"
# tags <tag> [existing git tags...]: the tags that would be published.
tags() {
  local tag="$1"
  shift
  if [[ $# -gt 0 ]]; then
    printf '%s\n' "$@" | "$TAGS" --existing - "$tag" 2>&1 | paste -sd' ' -
  else
    "$TAGS" "$tag" 2>&1 | paste -sd' ' -
  fi
}
kind() { "$TAGS" --kind "$1" 2>&1; }

# A release moves 4.0, 4 and latest.
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5)" "release: exact, minor, major, latest"
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5 4.0.3 4.0.4 4.0.5)" "release newer than every other tag"
# Pre-releases never move anything.
assert_eq "4.0.0-rc" "$(tags 4.0.0-rc)" "release candidate: exact tag only"
assert_eq "4.0.0-beta37" "$(tags 4.0.0-beta37)" "beta: exact tag only"
# An image revision (-rN) is a new image of the same Mukurtu release.
assert_eq "4.0.5-r1 4.0 4 latest" "$(tags 4.0.5-r1 4.0.5 4.0.5-r1)" "revision of the newest release moves the moving tags"
assert_eq "4.0.5-r2 4.0 4 latest" "$(tags 4.0.5-r2 4.0.5 4.0.5-r1 4.0.5-r2)" "a second revision is newer than the first"
assert_eq "4.0.5-r1" "$(tags 4.0.5-r1 4.0.5 4.0.5-r2 4.0.5-r1)" "an older revision published late moves nothing"
assert_eq "4.0.5-r1" "$(tags 4.0.5-r1 4.0.5 4.0.5-r1 4.0.6)" "a revision of an older release does not move tags back"
assert_eq "4.0.4-r1" "$(tags 4.0.4-r1 4.0.4 4.0.5 4.0.4-r1)" "backport: 4.0, 4 and latest stay on 4.0.5"
assert_eq "3.9.9-r1 3.9 3" "$(tags 3.9.9-r1 3.9.9 4.0.5 3.9.9-r1)" "older line: its own 3.9 and 3 move, latest does not"
assert_eq "4.1.0 4.1 4 latest" "$(tags 4.1.0 4.0.5 4.1.0)" "a new minor moves 4 and latest and starts 4.1"
assert_eq "4.0.5 4.0 4 latest" "$(tags 4.0.5 4.0.5 4.2.0-rc 4.x-dev v9.9.9 junk)" "pre-releases and junk tags are ignored"
assert_eq "4.0.5" "$(tags 4.0.5 4.0.5 4.0.5-r1)" "the plain tag is not re-pushed once a revision exists"
# Kinds.
assert_eq "stable" "$(kind 4.0.5)" "kind: stable"
assert_eq "revision" "$(kind 4.0.5-r1)" "kind: revision"
assert_eq "revision" "$(kind 4.0.5-r12)" "kind: revision with two digits"
assert_eq "prerelease" "$(kind 4.0.0-rc)" "kind: release candidate"
assert_eq "prerelease" "$(kind 4.0.0-rc1)" "kind: rc1 is a pre-release, not revision 'c1'"
assert_eq "prerelease" "$(kind 4.0.0-beta37)" "kind: beta"
# A development build is named after the commit it was built from, and moves only `edge`.
dev() { "$TAGS" --dev "$1" 2>&1 | paste -sd' ' -; }
assert_eq "4.x-dev-0123abc edge" "$(dev 0123abcdef0123abcdef0123abcdef0123abcdef)" "dev build: commit tag and edge, never latest"
assert_eq "4.x-dev-abcdef1 edge" "$(dev ABCDEF1234)" "dev build: the commit is lower-cased and shortened to 7"
for bad in "" "main" "0123" "xyz1234" "0123abc; rm"; do
  if "$TAGS" --dev "$bad" >/dev/null 2>&1; then fail "dev build rejects: '$bad'"; else pass "dev build rejects: '$bad'"; fi
done
# There is one image: no variant argument, no suffixes.
for bad in "4.x-dev" "v4.0.5" "4.0" "4.0.5-r0" "4.0.5-r01" "4.0.5 full" "4.0.5 slim" ""; do
  # shellcheck disable=SC2086
  if "$TAGS" $bad >/dev/null 2>&1; then fail "rejects: '$bad'"; else pass "rejects: '$bad'"; fi
done

echo "workflow: file"
if [[ ! -f "$WORKFLOW" ]]; then
  fail "docker-publish.yml exists"; finish; exit 1
fi
pass "docker-publish.yml exists"

# actionlint does not know the newest GitHub-hosted runner labels yet (ubuntu-26.04 and
# its arm64 twin are in GitHub's own list of hosted images). Ignore those messages, and
# nothing else; remove the -ignore when a newer actionlint lists the labels.
if docker run --rm -v "$REPO_ROOT:/repo:ro" -w /repo rhysd/actionlint:1.7.12 \
    -ignore 'label "ubuntu-26.04(-arm)?" is unknown' .github/workflows/docker-publish.yml >/tmp/actionlint.out 2>&1; then
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
if set(on) != {"release", "workflow_dispatch"}:
    errors.append(f"only release and workflow_dispatch may trigger a publish (got {sorted(on)})")
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

# One image, built natively on each architecture: the `build` job runs once per
# architecture, on a runner of that architecture, and `publish` joins the two.
build = jobs["build"]
publish = jobs["publish"]
for needle in ("WITH_FFMPEG", "WITH_POPPLER", "slim", "-slim"):
    if needle in text:
        errors.append(f"the workflow must not mention {needle}")
include = build.get("strategy", {}).get("matrix", {}).get("include", [])
if sorted(m.get("arch") for m in include) != ["amd64", "arm64"]:
    errors.append("build must have a matrix with exactly amd64 and arm64")
runners = {m.get("arch"): m.get("runner") for m in include}
if runners.get("amd64") != "ubuntu-26.04" or runners.get("arm64") != "ubuntu-26.04-arm":
    errors.append(f"build must run natively: ubuntu-26.04 for amd64 and ubuntu-26.04-arm for arm64 (got {runners})")
if build.get("runs-on") != "${{ matrix.runner }}":
    errors.append("build must take runs-on from the matrix")
if "qemu" in text.lower():
    errors.append("no QEMU: each architecture builds natively")

# What is published is the image that was tested. The steps that test and scan come
# before the step that pushes, in the same job, and the image is pushed with docker
# push, not rebuilt.
steps = build["steps"]
def index(pred):
    for i, st in enumerate(steps):
        if pred(st):
            return i
    return None
i_build = index(lambda st: "build-push-action" in st.get("uses", ""))
i_test = index(lambda st: "scripts/docker/test-" in st.get("run", ""))
i_scan = index(lambda st: "trivy" in st.get("uses", "").lower() and st.get("with", {}).get("exit-code") == "1")
i_push = index(lambda st: "docker push" in st.get("run", ""))
if None in (i_build, i_test, i_scan, i_push):
    errors.append(f"build needs a build, test, scan and push step (got {i_build} {i_test} {i_scan} {i_push})")
else:
    if not (i_build < i_test < i_push and i_build < i_scan < i_push):
        errors.append("build must build, then test and scan, then push")
    b = steps[i_build]["with"]
    if b.get("push") not in (False, "false", None) or str(b.get("load")).lower() != "true":
        errors.append("the build step must load the image locally (load: true), not push it")
    if "cache-from" in b or "cache-to" in b:
        errors.append("no build cache: the image is built from scratch each time")
    if "MUKURTU_TEST_IMAGE" not in steps[i_test].get("env", {}):
        errors.append("the test step must run against the image just built (MUKURTU_TEST_IMAGE)")
for flag in ("HIGH,CRITICAL", "ignore-unfixed", "exit-code"):
    if flag not in yaml.dump(steps[i_scan]):
        errors.append(f"trivy step lacks {flag}")

# Permissions: read-only by default, write only where it is needed.
if wf.get("permissions") != {"contents": "read"}:
    errors.append("the workflow default must be permissions: contents: read")
for jname in ("build", "publish"):
    if jobs[jname].get("permissions", {}).get("packages") != "write":
        errors.append(f"{jname} needs packages: write")
if jobs["resolve"].get("permissions", {}).get("packages"):
    errors.append("resolve must not have packages permission")

# The multi-arch tag is made by joining the two pushed images, and attested.
pub_text = yaml.dump(publish)
for needle in ("imagetools create", "attest-build-provenance"):
    if needle not in pub_text:
        errors.append(f"publish lacks {needle}")
if "attest-sbom" not in yaml.dump(build):
    errors.append("build must attest an SBOM for each architecture's image")
if "needs" not in publish or "build" not in (publish["needs"] if isinstance(publish["needs"], list) else [publish["needs"]]):
    errors.append("publish must need the build job")

inputs = on["workflow_dispatch"].get("inputs", {})
# The audit is strict unless someone asks, by hand, to go on when Drupal's advisory service
# is down. A release never asks.
if inputs.get("audit_unreachable", {}).get("default") not in (False, "false"):
    errors.append("the audit_unreachable input must exist and default to false")
if "COMPOSER_AUDIT_FLAGS" not in text:
    errors.append("the workflow must pass COMPOSER_AUDIT_FLAGS to the build and the tests")
if "--ignore-unreachable" not in text or "inputs.audit_unreachable" not in text:
    errors.append("COMPOSER_AUDIT_FLAGS must be --ignore-unreachable, and only when audit_unreachable is ticked")

# A development build is manual only, takes a commit, and never moves `latest`.
if "ref" not in inputs or "dev" not in inputs:
    errors.append("workflow_dispatch needs the inputs ref and dev")
if "compute-tags.sh --dev" not in text:
    errors.append("a development build must take its tags from compute-tags.sh --dev")

# Dependabot watches the Dockerfile and the actions.
ecosystems = {u["package-ecosystem"] for u in dep["updates"]}
for eco in ("docker", "docker-compose", "github-actions"):
    if eco not in ecosystems:
        errors.append(f"dependabot.yml lacks {eco}")

if errors:
    print("\n".join(errors), file=sys.stderr)
    sys.exit(1)
PY


DOC="$REPO_ROOT/docs/docker-build.md"

REQUIRED_SECTIONS=(
  "Pinned inputs"
  "Where the code comes from"
  "Runtime image, tools and extensions"
  "Configuration rendering"
  "Security hardening"
  "Performance tuning"
  "Drupal settings"
  "Startup, supervision and health"
  "Known hazards"
  "Hosting examples"
  "Publishing and supply chain"
  "Measured footprint"
)
if [[ ! -f "$DOC" ]]; then
  fail "docs/docker-build.md exists"; finish; exit 1
fi

echo "examples: the database image is pinned"
for f in "$REPO_ROOT"/examples/docker/*/docker-compose.yml; do
  image="$(sed -n 's/^ *image: *\(mariadb:[^ ]*\).*/\1/p' "$f" | head -1)"
  [[ "$image" =~ ^mariadb:[0-9]+\.[0-9]+(\.[0-9]+)?$ ]] && pass "$(basename "$(dirname "$f")"): MariaDB is pinned to a series ($image), not a moving major tag" || fail "$(basename "$(dirname "$f")"): MariaDB is pinned to a series (got '$image')"
done

echo "docs: docs/docker-build.md has a section for every part of the image"
for heading in "${REQUIRED_SECTIONS[@]}"; do
  if grep -qxF "## $heading" "$DOC"; then
    pass "section: $heading"
  else
    fail "section missing: $heading"
  fi
done


echo "docs: docs/docker.md agrees with the code"
REFERENCE="$REPO_ROOT/docs/docker.md"
if [[ ! -f "$REFERENCE" ]]; then
  fail "docs/docker.md exists"
  finish
  exit
fi

# Every MUKURTU_ name the image reads, in the code and in the reference. A name
# ending in _ is a prefix mentioned in a comment, not a variable.
names() { grep -rhoE 'MUKURTU_[A-Z0-9_]*[A-Z0-9]\b' "$@" 2>/dev/null | sort -u; }
code_names="$(names "$REPO_ROOT/Dockerfile" "$REPO_ROOT"/docker/*.sh "$REPO_ROOT"/docker/*.php \
  "$REPO_ROOT"/docker/templates "$REPO_ROOT"/examples/docker/*/docker-compose.yml "$REPO_ROOT"/examples/docker/*/.env.example "$REPO_ROOT"/examples/docker/*/generate-secrets.sh)"
doc_names="$(names "$REFERENCE")"

# Variables that exist only inside the image (template placeholders, pool tags):
# they are not settings and are deliberately undocumented.
INTERNAL="MUKURTU_FPM_PM_SETTINGS MUKURTU_MSMTP_SETTINGS MUKURTU_NGINX_ACCESS_LOG_DIRECTIVE MUKURTU_PHP_MAIL_SETTINGS MUKURTU_POOL"
# The generic _FILE handling means this one never appears literally in the code.
GENERATED="MUKURTU_SMTP_PASSWORD_FILE"

missing=""
for name in $code_names; do
  [[ " $INTERNAL " == *" $name "* ]] && continue
  grep -qx "$name" <<<"$doc_names" || missing="$missing $name"
done
[[ -z "$missing" ]] && pass "every variable in the code is documented" || fail "undocumented variables:$missing"

stale=""
for name in $doc_names; do
  [[ " $GENERATED " == *" $name "* ]] && continue
  grep -qx "$name" <<<"$code_names" || stale="$stale $name"
done
[[ -z "$stale" ]] && pass "every documented variable exists in the code" || fail "documented but not in the code:$stale"

# Defaults: a `: "${NAME:=value}"` line in a script must match the default
# column of that variable's row in the reference.
wrong=""
while IFS=$'\t' read -r name value; do
  [[ -z "$value" ]] && continue
  row="$(grep -E "^\| \`$name\` \|" "$REFERENCE" | head -1)"
  if [[ -z "$row" ]]; then
    wrong="$wrong $name(no table row)"
  elif [[ "$row" != *"\`$value\`"* ]]; then
    wrong="$wrong $name(code: $value)"
  fi
done < <(grep -hE '^: "\$\{MUKURTU_[A-Z0-9_]+:=' "$REPO_ROOT"/docker/*.sh \
  | sed -E 's/^: "\$\{(MUKURTU_[A-Z0-9_]+):=(.*)\}"$/\1\t\2/' | sort -u)
[[ -z "$wrong" ]] && pass "documented defaults match the code" || fail "default differs or row missing:$wrong"

# Each secret can be given as a file.
for secret in MUKURTU_DB_PASSWORD MUKURTU_ADMIN_PASSWORD MUKURTU_HASH_SALT MUKURTU_SMTP_PASSWORD; do
  grep -qx "${secret}_FILE" <<<"$doc_names" && pass "documents ${secret}_FILE" || fail "documents ${secret}_FILE"
done

finish
