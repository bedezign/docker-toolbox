find-docker () {
  path=$(pwd)
  while [[ "$path" != "" && ! -e "$path/docker-compose.yml" ]]; do
    path=${path%/*}
  done
  echo "$path"
}

function title {
    # Only when iTerm is active
    if [[ -n "$ITERM_SESSION_ID" ]]; then
        echo -ne "\033]0;"$*"\007"
    fi
}

# fail on any error
set -o errexit

# Find the docker compose file higher up, store it in APP, and include project settings
APP=$(find-docker)
source "$APP/.docker/settings.sh"
pushd "$APP" 1>/dev/null
