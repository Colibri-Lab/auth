#!/bin/sh

set -eu

if [ "$#" -ne 1 ]; then
    printf '%s\n' 'Usage: AUTH_SECURITY_TESTS=1 sh bin/auth-security-tests.sh <auth-host>' >&2
    exit 2
fi

case "$1" in
    ''|*[!a-zA-Z0-9._-]*|-*)
        printf '%s\n' 'Expected a configured Auth hostname without a scheme, port or path.' >&2
        exit 2
        ;;
esac

CURDIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
WEBROOT="$CURDIR/../web"
if [ ! -f "$WEBROOT/index.php" ]; then
    printf '%s\n' 'Install this launcher into the application bin directory before running it.' >&2
    exit 2
fi

cd "$WEBROOT"
REPORT=$(mktemp)
trap 'rm -f -- "$REPORT"' EXIT
trap 'exit 2' HUP INT TERM

if /usr/bin/php index.php "$1" /modules/auth/security-tests/run.json > "$REPORT"; then
    STATUS=0
else
    STATUS=$?
fi

/usr/bin/php -r '
try {
    $report = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($report) || ($report["cleanup"] ?? null) !== "complete"
        || !is_array($report["checks"] ?? null) || count($report["checks"]) === 0
        || !is_int($report["passed"] ?? null) || !is_int($report["failed"] ?? null)) {
        throw new RuntimeException("Invalid report");
    }
    $failed = 0;
    foreach ($report["checks"] as $check) {
        if (!is_bool($check["passed"] ?? null)) {
            throw new RuntimeException("Invalid assertion");
        }
        if (!$check["passed"]) {
            $failed++;
        }
    }
    $expected = $failed === 0 ? 0 : 1;
    if ($failed !== $report["failed"] || count($report["checks"]) - $failed !== $report["passed"]
        || (int)$argv[2] !== $expected) {
        throw new RuntimeException("Inconsistent report");
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit($expected);
} catch (Throwable $exception) {
    fwrite(STDERR, "No valid completed security report; bootstrap, transport or cleanup failed.\n");
    exit(2);
}
' "$REPORT" "$STATUS"
