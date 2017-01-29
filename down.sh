#!/usr/bin/env bash

SCRIPT_PATH=`dirname $0`
source "$SCRIPT_PATH/_includes.sh"

# Terminate matching socat instances (or experience a shitload of error messages)
echo "* Stopping socat processes... "
sudo pkill -f socat.+$IP

echo "* Terminating and removing containers ... "
docker-compose down
title default
popd