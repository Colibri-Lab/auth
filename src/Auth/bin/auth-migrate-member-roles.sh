#!/bin/sh

set -eu

if [ "$#" -ne 1 ]; then
    printf '%s\n' 'Usage: sh bin/auth-migrate-member-roles.sh <manage-host>' >&2
    exit 1
fi

HOST=$1
CURDIR=$(dirname "$0")
cd "$CURDIR/../web"
REPORT=$(mktemp)
trap 'rm -f -- "$REPORT"' EXIT
trap 'exit 1' HUP INT TERM
/usr/bin/php index.php "$HOST" /modules/auth/backend/migrate-member-roles.json > "$REPORT"
/usr/bin/php -r '
try {
    $report = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (($report["role_type"] ?? null) !== "json") {
        throw new RuntimeException("Invalid migration report");
    }
    echo json_encode($report, JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "Role migration did not produce a verified report. Inspect application logs before continuing.\n");
    exit(1);
}
' "$REPORT"
