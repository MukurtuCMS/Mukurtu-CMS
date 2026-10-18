#!/usr/bin/env bash
# docs/docker-build.md grows with the implementation: every finished piece of
# the image must have its section. Add a heading to REQUIRED_SECTIONS in the
# same commit that adds the section.
#
# Usage: test-docs.sh [heading...]   (arguments replace the default list)
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

DOC="$REPO_ROOT/docs/docker-build.md"

REQUIRED_SECTIONS=(
  "Pinned inputs and the build guard"
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
)
if [[ $# -gt 0 ]]; then
  REQUIRED_SECTIONS=("$@")
fi

if [[ ! -f "$DOC" ]]; then
  fail "docs/docker-build.md exists"
  finish
  exit
fi

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
  "$REPO_ROOT"/docker/templates "$REPO_ROOT"/docker-compose*.yml "$REPO_ROOT/.github/workflows/docker-publish.yml")"
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
