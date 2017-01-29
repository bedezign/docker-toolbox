<?php
/**
 * The new docker for OSX opens all ports on the OSX lo0 interface.
 * This means you are forced to use urls like http://localhost:32580/ for your site.
 * This script adds an alias IP to the lo0 interface (one you configure via the environment / .env file or by
 * modifying the configuration below).
 * Based on docker-compose "rules" it tries to guess the network created for the current folder so that
 * it can use the docker inspect functionality to obtain all related containers and the ports they provide.
 * Finally it uses the `socat`-tool to proxy ports on that virtual IP to the original port on localhost.
 */

if ($argc !== 3) {
    echo "Usage: php ip-proxy.php [host] [ip]" . PHP_EOL;
    exit;
}
$host = $argv[1];
$ip   = $argv[2];

$network  = preg_replace('/[\W_]/', '', basename(getcwd())) . '_default';       // Assumed network name, update manually if incorrect
$sudo     = '/usr/bin/sudo';
$docker   = "/usr/local/bin/docker";
$compose  = '/usr/local/bin/docker-compose';
$socat    = "$sudo /usr/local/bin/socat";
$ifconfig = "$sudo /sbin/ifconfig";
$lo       = 'lo0';

foreach (['killSocatInstances', 'removeLoAlias', 'addLoAlias', 'addPortForwards', 'addDockerHostEntry', 'addHostEntry'] as $function) {
    $function();
}

function removeLoAlias()
{
    global $ifconfig, $ip, $lo;
    echo "> remove $lo alias..." . PHP_EOL;
    exec("$ifconfig lo0 $ip delete 2>/dev/null");
}

function addLoAlias()
{
    global $ifconfig, $ip, $lo;
    echo "> add $lo alias..." . PHP_EOL;
    exec("$ifconfig lo0 $ip alias");
}

/**
 * Make sure no socat processes are running
 */
function killSocatInstances()
{
    global $ip;
    echo "> Terminating all socat instances..." . PHP_EOL;
    // Obtain all related socat processes for the current docker "machine"
    $processes = array_filter(explode(PHP_EOL, trim(`pgrep -f socat.+$ip`)));
    if (count($processes)) {
        exec('sudo kill -9 ' . implode(' ', $processes));
        sleep(2);
    }
}

/**
 * Iterates through all containers and detects the ports being forwarded
 * It will then use socat to create a redirect from "localhost:host-port" to "docker-host-ip:exported-port" for you
 */
function addPortForwards()
{
    global $host, $ip, $socat, $network;

    $aliasIp = $GLOBALS['ip'];

    // Use the network inspection to obtain list of all involved machines/containers
    echo "> Adding container port forwards, obtaining container list via network '$network'..." . PHP_EOL;
    $networkConfig = dockerInspect($network, 'network');
    foreach ($networkConfig['Containers'] as $container) {
        echo ">> Inspecting container '{$container['Name']}'..." . PHP_EOL;

        $containerConfig = dockerInspect($container['Name']);
        $ports           = array_get($containerConfig, 'NetworkSettings.Ports');
        if ($ports) {
            foreach ($ports as $port => $portConfig) {
                if (!$portConfig) {
                    // No host config = exposed port
                    continue;
                }

                list($port, $proto) = explode('/', $port);
                if ($proto === 'tcp') {
                    // If no HostPort is present its a "one on one"
                    $hostPort = array_get($portConfig, '0.HostPort', $port);
                    if ($port && $aliasIp && $hostPort) {
                        echo ">>> 0.0.0.0:$hostPort -> $aliasIp:$port" . PHP_EOL;
                        
                        // Extra check if there is no application with the port open
                        $sockets = array_filter(explode(PHP_EOL, trim(`lsof -i tcp:$port`)));
                        $bound = false;
                        foreach ($sockets as $socket) {
                        	if ((strpos($socket, "->$host:$port") !== false || strpos($socket, "->$ip:$port") !== false) && strpos($socket, 'CLOSE_WAIT')) {
                        		echo ">>>> Port still in use, skipping bind: '$socket'" . PHP_EOL;
                        		$bound = true;
                        	}	
                        } 
                        
                        // Note to self: Can only run in background if output gets redirected
                        if (!$bound)
                           exec("$socat tcp4-listen:$port,fork,bind=$aliasIp tcp4:127.0.0.1:$hostPort >/dev/null &");
                    }
                }
            }
        } else {
            echo ">>> No ports found. Skipping." . PHP_EOL;
        }
    }
}

/**
 * To get things like XDebug etc working we need an IP to connect back to.
 * This gives all containers a hosts entry to use for that purpose.
 * Docker re-creates the /etc/hosts every boot.
 */
function addDockerHostEntry()
{
    global $docker, $network, $ip, $host;

    echo "> Adding '$host' hosts entry to containers ..." . PHP_EOL;
    $networkConfig = dockerInspect($network, 'network');
    foreach ($networkConfig['Containers'] as $container) {
        echo ">> '{$container['Name']}'..." . PHP_EOL;
        `$docker exec -i {$container['Name']} /bin/bash -c "echo $ip $host >> /etc/hosts"`;
    }
}


function addHostEntry()
{
    global $sudo, $ip, $host;

    echo "> Adding '$host' hosts entry to /etc/hosts ... ";
    $hosts = file('/etc/hosts');
    foreach ($hosts as $index => $existingHost) {
        if (strpos($existingHost, $host) !== false) {
            if (substr(trim($existingHost), 0, 1) == '#') {
                continue;
            }

            // IP matches?
            if (strpos($existingHost, $ip) !== false) {
                echo "found and up to date." . PHP_EOL;
                return;
            } else {
                // Remove the line
                echo "found but outdated (please remove the old one yourself) ... ";
                unset($hosts[$index]);
                break;
            }
        }
    }

    // Add extra line to the file
    exec("echo \"$ip\t$host\" | $sudo tee -a /etc/hosts");
    echo "added" . PHP_EOL;
}

function dockerInspect($what, $type = '')
{
    global $docker;
    $result = json_decode(`$docker $type inspect $what`, true);
    return $result[0];
}

function array_get($array, $key, $default = null)
{
    if (!is_array($array)) {
        return $default;
    }
    if (is_null($key)) {
        return $array;
    }
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }

    foreach (explode('.', $key) as $segment) {
        if (is_array($array) && array_key_exists($segment, $array)) {
            $array = $array[$segment];
        } else {
            return $default;
        }
    }

    return $array;
}