#!/usr/bin/env bash
#
# Verify the extension: static analysis at level 8 and the unit suite.
#
# Two modes, chosen automatically:
#
#   1. local   — when `composer install` has been run (vendor/bin/phpstan and
#                vendor/bin/phpunit exist). Nothing else is needed.
#
#   2. remote  — otherwise, the sources are packed and executed inside a
#                running EspoCRM container over `railway ssh`, using the same
#                PHP version the instance runs. The PHARs are fetched there.
#                Set VERIFY_RAILWAY_ARGS to the connection arguments:
#
#                  VERIFY_RAILWAY_ARGS="-p <project-id> -e <env-id> -s EspoCRM" ./tools/verify.sh
#
# Exit code is 0 only when both checks pass.
#
# This replaces CI. GitHub Actions is not usable on this account (jobs are
# rejected at registration with a billing lock), and a gate that silently never
# runs is worse than no gate.

set -euo pipefail

# Git Bash on Windows rewrites arguments that look like Unix paths (MSYS2 path
# conversion). The remote commands below contain paths that must reach the
# container untouched, so switch the conversion off.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RAILWAY_ARGS="${VERIFY_RAILWAY_ARGS:-}"
PHPSTAN_LEVEL="${PHPSTAN_LEVEL:-8}"

run_local() {
    echo "==> mode: local (composer toolchain)"

    echo "==> PHPStan level ${PHPSTAN_LEVEL}"
    vendor/bin/phpstan analyse --no-progress

    echo "==> PHPUnit"
    vendor/bin/phpunit tests/unit
}

run_remote() {
    echo "==> mode: remote (${RAILWAY_ARGS})"

    # Payloads go over stdin, not as command arguments: `railway ssh` truncates
    # long arguments, which corrupts the command on the other side.
    # shellcheck disable=SC2086
    tar -czf - src/files/custom tests/unit tools/verify-bootstrap.php \
        | railway ssh $RAILWAY_ARGS -- \
            "rm -rf /tmp/holista-verify && mkdir -p /tmp/holista-verify && tar -xzf - -C /tmp/holista-verify" \
            >/dev/null

    # shellcheck disable=SC2086
    sed "s/PHPSTAN_LEVEL_PLACEHOLDER/${PHPSTAN_LEVEL}/g" tools/verify-remote.sh \
        | railway ssh $RAILWAY_ARGS -- \
            "cat > /tmp/holista-verify.sh; bash /tmp/holista-verify.sh; rc=\$?; rm -f /tmp/holista-verify.sh; exit \$rc"
}

if [ -x vendor/bin/phpstan ] && [ -x vendor/bin/phpunit ]; then
    run_local
elif [ -n "$RAILWAY_ARGS" ]; then
    run_remote
else
    cat >&2 <<'MSG'
Cannot verify: no local Composer toolchain and VERIFY_RAILWAY_ARGS is unset.

Either:
  composer install && ./tools/verify.sh
or:
  VERIFY_RAILWAY_ARGS="-p <project-id> -e <env-id> -s EspoCRM" ./tools/verify.sh
MSG
    exit 2
fi

echo "==> OK"
