#!/usr/bin/env bash
# Image tags for a release tag.
#
#   compute-tags.sh [--existing FILE|-] <tag>   print the tags to publish
#   compute-tags.sh --kind <tag>                            print stable, revision or prerelease
#
# Three kinds of release tag exist:
#
#   4.0.5        stable      a Mukurtu release
#   4.0.5-r1     revision    a new image of the same Mukurtu release, to ship a fix to
#                            the platform (base image, PHP, nginx, ...). The Mukurtu
#                            code is unchanged. -r1, -r2, ... count up from 1.
#   4.0.0-rc     prerelease  anything else with a hyphen (4.0.0-rc1, 4.0.0-beta37)
#
# Every kind publishes its exact tag. Stable and revision tags also publish the
# moving tags 4.<minor> (here 4.0), 4, and latest, but each moving tag only
# advances: it is published only if this tag is the newest stable-or-revision tag
# in its scope (the same minor, the same major, everything). That is what keeps a
# backport (4.0.4-r1, published after 4.0.5) from dragging `latest` backwards.
# "Newest" is judged against the other tags passed with --existing, one per line;
# tags that are not stable or revision tags are ignored.
#
# A published tag is never re-pushed by this workflow, so the plain tag 4.0.5 stays
# the image it was; a pinned deployment moves to 4.0.5-r1 on purpose.
set -euo pipefail

# parse <tag>: sets KIND, MAJOR, MINOR, PATCH, REV. Returns 1 if not a release tag.
parse() {
  local tag="$1"
  KIND="" MAJOR="" MINOR="" PATCH="" REV=0
  if [[ "$tag" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
    KIND=stable
  elif [[ "$tag" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)-r([1-9][0-9]*)$ ]]; then
    KIND=revision
    REV="${BASH_REMATCH[4]}"
  elif [[ ! "$tag" =~ -r[0-9]+$ ]] && [[ "$tag" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)-[0-9A-Za-z.-]+$ ]]; then
    # (-r0 and -r01 end in -r<digits> but are not valid revisions: rejected.)
    KIND=prerelease
  else
    return 1
  fi
  MAJOR="${BASH_REMATCH[1]}" MINOR="${BASH_REMATCH[2]}" PATCH="${BASH_REMATCH[3]}"
}

# key <major> <minor> <patch> <rev>: a string that sorts in release order.
key() { printf '%09d%09d%09d%09d' "$1" "$2" "$3" "$4"; }

if [[ "${1:-}" == "--kind" ]]; then
  if [[ $# -ne 2 ]] || ! parse "$2"; then
    echo "ERROR: not a release tag such as 4.0.5, 4.0.5-r1 or 4.0.0-rc (no leading v): '${2:-}'" >&2
    exit 1
  fi
  echo "$KIND"
  exit 0
fi

existing_file=""
if [[ "${1:-}" == "--existing" ]]; then
  [[ $# -ge 2 ]] || { echo "usage: compute-tags.sh [--existing FILE|-] <tag>" >&2; exit 1; }
  existing_file="$2"
  shift 2
fi
if [[ $# -ne 1 ]]; then
  echo "usage: compute-tags.sh [--existing FILE|-] <tag>" >&2
  exit 1
fi
tag="$1"

if ! parse "$tag"; then
  echo "ERROR: not a release tag such as 4.0.5, 4.0.5-r1 or 4.0.0-rc (no leading v): '$tag'" >&2
  exit 1
fi
this_kind="$KIND" major="$MAJOR" minor="$MINOR"
this_key="$(key "$MAJOR" "$MINOR" "$PATCH" "$REV")"

echo "$tag"
[[ "$this_kind" == prerelease ]] && exit 0

# The newest key in each scope, among the existing stable and revision tags.
newest_minor="$this_key" newest_major="$this_key" newest_all="$this_key"
if [[ -n "$existing_file" ]]; then
  while IFS= read -r other; do
    parse "$other" || continue
    [[ "$KIND" == prerelease ]] && continue
    other_key="$(key "$MAJOR" "$MINOR" "$PATCH" "$REV")"
    [[ "$other_key" > "$newest_all" ]] && newest_all="$other_key"
    if [[ "$MAJOR" == "$major" ]]; then
      [[ "$other_key" > "$newest_major" ]] && newest_major="$other_key"
      if [[ "$MINOR" == "$minor" ]]; then
        [[ "$other_key" > "$newest_minor" ]] && newest_minor="$other_key"
      fi
    fi
  done < <(if [[ "$existing_file" == "-" ]]; then cat; else cat "$existing_file"; fi)
fi

[[ "$this_key" == "$newest_minor" ]] && echo "${major}.${minor}"
[[ "$this_key" == "$newest_major" ]] && echo "$major"
[[ "$this_key" == "$newest_all" ]] && echo "latest"
exit 0
