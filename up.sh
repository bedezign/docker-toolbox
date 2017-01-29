#!/usr/bin/env bash

# run from the project root
SCRIPT_PATH=`dirname $0`
source "$SCRIPT_PATH/_includes.sh"

title $TITLE

echo "* Starting containers ... "
docker-compose up -d

"$SCRIPT_PATH/proxy.sh"

popd
