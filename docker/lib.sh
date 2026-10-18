#!/bin/sh
# Helpers shared by the container's scripts. Source this file; it sets nothing.
# POSIX sh only: the runtime image has busybox ash, not bash.

die() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

warn() {
  printf 'WARNING: %s\n' "$*" >&2
}

# env_or_file NAME: print the value of $NAME, or the contents of the file named
# by ${NAME}_FILE (for Docker/Compose secrets). The file wins when both are set.
# A file's trailing newline is dropped by the caller's command substitution.
env_or_file() {
  _eof_name=$1
  eval "_eof_file=\${${_eof_name}_FILE:-}"
  if [ -n "$_eof_file" ]; then
    [ -r "$_eof_file" ] || die "${_eof_name}_FILE is '$_eof_file', which is not readable"
    cat "$_eof_file"
  else
    eval "printf '%s' \"\${${_eof_name}:-}\""
  fi
}

# load_secret NAME: set and export $NAME from env_or_file, so later code (and
# anything it starts) sees one resolved value.
load_secret() {
  _ls_value=$(env_or_file "$1") || exit 1
  eval "$1=\$_ls_value"
  eval "export $1"
}

# require_env NAME: fail unless $NAME is set and not empty.
require_env() {
  eval "_re_value=\${$1:-}"
  [ -n "$_re_value" ] || die "$1 is required"
}

# The validators check the *value* of the named variable. A value with a line
# break is always refused: `grep` matches line by line, so "100M<newline>evil = 1"
# would pass a pattern check and then inject a second line into a rendered
# configuration file.
_NL='
'
_CR=$(printf '\r')
_validate() { # _validate NAME PATTERN DESCRIPTION
  eval "_v_value=\${$1:-}"
  case "$_v_value" in
    *"$_NL"*|*"$_CR"*) die "$1 must not contain line breaks" ;;
  esac
  printf '%s' "$_v_value" | grep -Eq "$2" || die "$1 must be $3 (got '$_v_value')"
}

validate_size() { _validate "$1" '^[0-9]+[KMG]$' "a size such as 128M or 1G"; }
validate_memory_limit() { _validate "$1" '^(-1|[0-9]+[KMG])$' "a size such as 512M, or -1 for no limit"; }
validate_int() { _validate "$1" '^[0-9]+$' "a whole number"; }
validate_positive_int() { _validate "$1" '^[1-9][0-9]*$' "a whole number greater than 0"; }
validate_bool() { _validate "$1" '^(true|false)$' "true or false"; }
validate_duration() { _validate "$1" '^[0-9]+[smhd]?$' "a duration such as 10s or 5m"; }
validate_abs_path() { _validate "$1" '^/[A-Za-z0-9_./-]*$' "an absolute path"; }
validate_no_spaces() { _validate "$1" '^[^[:space:]]+$' "a single word without spaces"; }
validate_timezone() { _validate "$1" '^[A-Za-z0-9_+/-]+$' "a time zone name such as UTC or Pacific/Auckland"; }

validate_choice() { # validate_choice NAME choice...
  _vc_name=$1
  shift
  eval "_vc_value=\${$_vc_name:-}"
  for _vc_choice in "$@"; do
    [ "$_vc_value" = "$_vc_choice" ] && return 0
  done
  die "$_vc_name must be one of: $* (got '$_vc_value')"
}

# render_template TEMPLATE OUTPUT: copy TEMPLATE to OUTPUT, replacing each
# @@NAME@@ with the value of the exported variable NAME. Values go through awk's
# ENVIRON, never through the shell or sed, so characters such as $ " ' \ & in a
# value arrive unchanged. A placeholder naming an unset variable is an error.
render_template() {
  awk '
    {
      line = $0
      out = ""
      while ((start = index(line, "@@")) > 0) {
        rest = substr(line, start + 2)
        len = index(rest, "@@")
        if (len == 0) break
        name = substr(rest, 1, len - 1)
        if (!(name in ENVIRON)) {
          printf "ERROR: template %s uses unset variable %s\n", FILENAME, name > "/dev/stderr"
          failed = 1
        }
        out = out substr(line, 1, start - 1) ENVIRON[name]
        line = substr(rest, len + 2)
      }
      print out line
    }
    END { exit failed }
  ' "$1" > "$2"
}
