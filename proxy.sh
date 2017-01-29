#!/usr/bin/env bash

SCRIPT_PATH=`dirname $0`
source "$SCRIPT_PATH/_includes.sh"

echo "* Proxying docker container ports ... "
php "$SCRIPT_PATH/localhost-proxy.php" $HOST $IP
popd