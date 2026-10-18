#!/usr/bin/env bash
# Print the image tags to publish for a release, one per line.
#
#   compute-tags.sh <version> <full|slim>
#
# A release (4.0.5) gets its exact version plus the moving tags 4.0, 4 and
# latest. A pre-release (anything with a hyphen: 4.0.0-rc, 4.0.0-beta37) gets
# only its exact version, so it can never become what `latest` or `4` pulls.
# The slim variant appends -slim to every tag (latest becomes latest-slim).
set -euo pipefail

if [[ $# -ne 2 ]]; then
  echo "usage: compute-tags.sh <version> <full|slim>" >&2
  exit 1
fi
version="$1"
variant="$2"

if [[ ! "$version" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)(-[0-9A-Za-z.-]+)?$ ]]; then
  echo "ERROR: version must look like 4.0.5 or 4.0.0-rc (no leading v), got '$version'" >&2
  exit 1
fi
major="${BASH_REMATCH[1]}"
minor="${BASH_REMATCH[2]}"
prerelease="${BASH_REMATCH[4]}"

case "$variant" in
  full) suffix="" ;;
  slim) suffix="-slim" ;;
  *) echo "ERROR: variant must be 'full' or 'slim', got '$variant'" >&2; exit 1 ;;
esac

echo "${version}${suffix}"
if [[ -z "$prerelease" ]]; then
  echo "${major}.${minor}${suffix}"
  echo "${major}${suffix}"
  echo "latest${suffix}"
fi
