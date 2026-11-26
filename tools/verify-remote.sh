#!/usr/bin/env bash
#
# Executed inside the EspoCRM container by tools/verify.sh.
#
# Expects /tmp/holista-verify to already contain:
#   src/files/custom/...        the module
#   tests/unit/...              the unit suite
#   tools/verify-bootstrap.php  PSR-4 autoloader for the no-Composer case
#
# PHPSTAN_LEVEL_PLACEHOLDER is replaced with the requested level before upload.
# The PHARs are cached in /tmp so repeat runs do not re-download.

set -eu

work=/tmp/holista-verify

if [ ! -d "$work/src" ]; then
    echo "payload missing at $work" >&2
    exit 2
fi

test -f /tmp/phpunit.phar || curl -fsSLo /tmp/phpunit.phar https://phar.phpunit.de/phpunit-12.phar
test -f /tmp/phpstan.phar || curl -fsSLo /tmp/phpstan.phar https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar

# EspoCRM is installed at /var/www/html in the official image; that source is used
# for type resolution so the analysis matches the running version.
espocrm_root=${ESPOCRM_ROOT:-/var/www/html}

if [ ! -d "$espocrm_root/application" ]; then
    echo "EspoCRM source not found at $espocrm_root/application" >&2
    exit 2
fi

cat > "$work/phpstan.neon" <<EOF
parameters:
    level: PHPSTAN_LEVEL_PLACEHOLDER
    inferPrivatePropertyTypeFromConstructor: true
    treatPhpDocTypesAsCertain: false
    paths:
        - $work/src/files/custom
    reportUnmatchedIgnoredErrors: false
    scanDirectories:
        - $espocrm_root/application
        - $espocrm_root/vendor
EOF

cd "$work"

echo "==> PHPStan level PHPSTAN_LEVEL_PLACEHOLDER (against $espocrm_root)"
php /tmp/phpstan.phar analyse -c "$work/phpstan.neon" --no-progress --error-format=table

echo "==> PHPUnit"
php /tmp/phpunit.phar --no-configuration --bootstrap "$work/tools/verify-bootstrap.php" --colors=never tests/unit
