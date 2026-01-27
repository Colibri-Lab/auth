#!/bin/sh

CURDIR=$(dirname $0)
if [ "$CURDIR" = '.' ]
then
    CURDIR=`pwd` 
fi
cd $CURDIR
cd ../web && /usr/bin/php index.php $1 /modules/auth/bundle-all.json
