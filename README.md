# Docker Compose Toolbox

This toolkit contains helper scripts for the native docker client for macOs. 
Things I saw myself doing again and again.

It started from the inconveniences I experienced with having to use - either random port number if you wish to run multiple projects at a time - or only being able to run a single project at a time. (Did I say I really don't like port numbers?)
I [blogged about it here](http://blog.blizzke.com/2016/06/docker-for-osx-lotsa-wows-couple-ohws.html) a while ago, which resulted in the first version of my proxy script.

I simply alias all these commands to `docker-<name of the script>`. 
Since ZSH's autocompletion is excellent, allowing me to simply type `d-up` `tab` to get to the up script etc (or `do-do` for docker down... how convenient).

BTW: Since all scripts will enumerate the directory structure upwards in search of a `docker-compose.yml` to determine your project root, you can simply run them 
from any subfolder in your project.

## Proxying

The main functionality of this kit is the proxy functionality. It will create an aliased IP for your localhost interface and then query the docker container structure of your project in order to find all exposed ports and what port they were linked to on the Docker VM. Those exposed ports will then be linked between your aliased IP and the Docker VM. This gives you a separate URL for your project with original port numbers intact.

So instead of having to type _http://localhost:32875_ to get to your projects' HTTP container, you can now simply type _http://mywebsite.dev_.

Firing the `up` script will yield output like this (assuming the example configuration below):

```BASH
$ docker-up                                                                                                                                                                                                                                                                   * Starting containers ...
mywebsitedev_mail_1 is up-to-date
mywebsitedev_redis_1 is up-to-date
mywebsitedev_php_1 is up-to-date
mywebsitedev_nginx_1 is up-to-date
* Proxying docker container ports ...
> Terminating all socat instances...
Password:
> remove lo0 alias...
> add lo0 alias...
> Adding container port forwards, obtaining container list via network 'mywebsitedev_default'...
>> Inspecting container 'mywebsitedev_nginx_1'...
>>> 0.0.0.0:32773 -> 172.99.0.1:443
>>> 0.0.0.0:32774 -> 172.99.0.1:80
>> Inspecting container 'mywebsitedev_mail_1'...
>>> 0.0.0.0:32771 -> 172.99.0.1:143
>>> 0.0.0.0:32772 -> 172.99.0.1:25
>>> 0.0.0.0:32770 -> 172.99.0.1:587
>>> 0.0.0.0:32769 -> 172.99.0.1:993
>> Inspecting container 'mywebsitedev_mysql_1'...
>>> 0.0.0.0:32768 -> 172.99.0.1:3306
>> Inspecting container 'mywebsitedev_php_1'...
> Adding '9maand.dev' hosts entry to containers ...
>> 'mywebsitedev_nginx_1'...
>> 'mywebsitedev_mail_1'...
>> 'mywebsitedev_redis_1'...
>> 'mywebsitedev_php_1'...
> Adding 'mywebsite.dev' hosts entry to /etc/hosts ... found and up to date.
```

## Requirements

The proxy script relies on the `socat` tool being installed (if you are running _homebrew_ it's as simple as `brew install socat`) for the proxying between your localhost aliased IP and the actual docker containers.

You should create a `.docker/settings.sh` file in your project (see below for the syntax).

For the `shell` script, some string-case functionality is used, so you will probably need at least bash v4 or another shell with similar functionality.

## Commands

### up.sh

This project is a wrapper around `docker-compose up`, but it will also fire up the proxy functionality

### down.sh

Obviously the opposity, but it will terminate all `socat` instances as well, to prevent that massive dump of error messages about ports not being available.

### restart.sh

See if you can guess it.

### shell.sh

Start a bash shell in any of the containers you defined in your settings file.

### proxy.sh

Fires the `localhost-proxy.php` script with the `HOST` and `IP` values from your settings file. It's the php script that will take care the functionality.

### _includes.sh

Helper script.

## .docker/settings.sh

The scripts expect a `./.docker/settings.sh` file in your project (relative to the `docker-compose.yml`-file) that contains the settings for that specific project.

A typical `settings.sh` file might look like this:

```
#!/usr/bin/env bash

HOST=mywebsite.dev
IP=172.99.0.1

TITLE="MyWebsite[DOCKER]"

# Container data for the shell script. Default is "SHELL_" (when no container specified), but you can specify any of the others as argument (php, mongo, mysql)
SHELL_CONTAINER=mywebsitedev_php_1
SHELL_TITLE="MyWebsite[PHP]"

PHP_CONTAINER=$SHELL_CONTAINER
PHP_TITLE=$SHELL_TITLE

MONGO_CONTAINER=mywebsitedev_mongo_1
MONGO_TITLE="MyWebsite[MongoDB]"

MYSQL_CONTAINER=mywebsitedev_percona_1
MYSQL_TITLE="MyWebsite[MySQL]"

MAIL_CONTAINER=mywebsitedev_mail_1
MAIL_TITLE="MyWebsite[MAIL]"
```

The `HOST` and `IP` are mainly for the proxy functionality and can be chosen freely. They are added for you to the `/etc/hosts` file at some point.

The rest of the data is to determine which containers your project has, mainly for the shell functionality.
Everything ending in `_CONTAINER` is (d'oh) the name of the docker container (usually your folder name with only letters and numbers, followed by underscore and the name of the container in your `yml` file, followed by `_1` for the first container). 

Everything ending in `_TITLE` is what will be set as tab-title if you are running iTerm2.

The default container (if you use `docker-shell`) without arguments are set by `SHELL_CONTAINER`. The other containers can be used by simply running the shel and specifying the part before the first underscore. So for example `docker-shell mongo` would, in the case of the above configuration, run a shell in the `mywebsitedev_mongo_1` container. The settings above allow for `php`, `mongo`, `mysql` and `mail` as arguments for the shell script.
 
 