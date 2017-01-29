#!/usr/bin/env bash

SCRIPT_PATH=`dirname $0`
source "$SCRIPT_PATH/_includes.sh"

CONTAINER=$SHELL_CONTAINER
TITLE=$SHELL_TITLE

if [ ! -z "$1" ]; then
    PREFIX=$1
    PREFIX=${PREFIX^^}
    CONTAINER="${PREFIX}_CONTAINER"
    TITLE="${PREFIX}_TITLE"

    CONTAINER="${!CONTAINER}"
    TITLE="${!TITLE}"
fi

echo "Starting /bin/bash in the '$CONTAINER'-container. "
title $TITLE
docker exec -it $CONTAINER /bin/bash

popd
exit 0
