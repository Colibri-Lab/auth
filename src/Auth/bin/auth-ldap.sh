#!/bin/sh

set -eu

if [ "$#" -lt 2 ]; then
    printf '%s\n' 'Usage: sh bin/auth-ldap.sh <auth-host> <application> [listen=tcp://127.0.0.1:1389] [cert=/path/fullchain.pem] [key=/path/key.pem] [allow-insecure=true]' >&2
    exit 1
fi

HOST=$1
APPLICATION=$2
shift 2

CURDIR=$(dirname "$0")
cd "$CURDIR/../web"
exec /usr/bin/php index.php "$HOST" /modules/auth/ldap/start.json "application=$APPLICATION" "$@"