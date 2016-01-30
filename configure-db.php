#!/usr/bin/env php
<?php

$confpath = '/var/www/config.php';

$config = array();

// path to ttrss
$config['SELF_URL_PATH'] = env('SELF_URL_PATH', 'http://localhost');

if (getenv('DB_TYPE') !== false) {
    $config['DB_TYPE'] = getenv('DB_TYPE');
} elseif (getenv('DB_PORT_5432_TCP_ADDR') !== false) {
    // postgres container linked
    $config['DB_TYPE'] = 'pgsql';
    $eport = 5432;
} elseif (getenv('DB_PORT_3306_TCP_ADDR') !== false) {
    // mysql container linked
    $config['DB_TYPE'] = 'mysql';
    $eport = 3306;
}

if (!empty($eport)) {
    $config['DB_HOST'] = env('DB_PORT_' . $eport . '_TCP_ADDR');
    $config['DB_PORT'] = env('DB_PORT_' . $eport . '_TCP_PORT');
} elseif (getenv('DB_PORT') === false) {
    error('The env DB_PORT does not exist. Make sure to run with "--link mypostgresinstance:DB"');
} elseif (is_numeric(getenv('DB_PORT')) && getenv('DB_HOST') !== false) {
    // numeric DB_PORT provided; assume port number passed directly
    $config['DB_HOST'] = env('DB_HOST');
    $config['DB_PORT'] = env('DB_PORT');

    if (empty($config['DB_TYPE'])) {
        switch ($config['DB_PORT']) {
            case 3306:
                $config['DB_TYPE'] = 'mysql';
                break;
            case 5432:
                $config['DB_TYPE'] = 'pgsql';
                break;
            default:
                error('Database on non-standard port ' . $config['DB_PORT'] . ' and env DB_TYPE not present');
        }
    }
}

// database credentials for this instance
//   database name (DB_NAME) can be supplied or detaults to "ttrss"
//   database user (DB_USER) can be supplied or defaults to database name
//   database pass (DB_PASS) can be supplied or defaults to database user
$config['DB_NAME'] = env('DB_NAME', 'ttrss');
$config['DB_USER'] = env('DB_USER', $config['DB_NAME']);
$config['DB_PASS'] = env('DB_PASS', $config['DB_USER']);

if (!dbcheck($config)) {
    echo 'Database login failed, trying to create...' . PHP_EOL;
    // superuser account to create new database and corresponding user account
    //   username (SU_USER) can be supplied or defaults to "docker"
    //   password (SU_PASS) can be supplied or defaults to username

    $super = $config;

    $super['DB_NAME'] = null;
    $super['DB_USER'] = env('DB_ENV_USER', 'docker');
    $super['DB_PASS'] = env('DB_ENV_PASS', $super['DB_USER']);
    
    $pdo = dbconnect($super);

    if ($super['DB_TYPE'] === 'mysql') {
        $pdo->exec('CREATE DATABASE ' . ($config['DB_NAME']));
        $pdo->exec('GRANT ALL PRIVILEGES ON ' . ($config['DB_NAME']) . '.* TO ' . $pdo->quote($config['DB_USER']) . '@"%" IDENTIFIED BY ' . $pdo->quote($config['DB_PASS']));
    } else {
        $pdo->exec('CREATE ROLE ' . ($config['DB_USER']) . ' WITH LOGIN PASSWORD ' . $pdo->quote($config['DB_PASS']));
        $pdo->exec('CREATE DATABASE ' . ($config['DB_NAME']) . ' WITH OWNER ' . ($config['DB_USER']));
    }

    unset($pdo);
    
    if (dbcheck($config)) {
        echo 'Database login created and confirmed' . PHP_EOL;
    } else {
        error('Database login failed, trying to create login failed as well');
    }
}

$pdo = dbconnect($config);
try {
    $pdo->query('SELECT 1 FROM ttrss_feeds');
    // reached this point => table found, assume db is complete
}
catch (PDOException $e) {
    echo 'Database table not found, applying schema... ' . PHP_EOL;
    $schema = file_get_contents('schema/ttrss_schema_' . $config['DB_TYPE'] . '.sql');
    $schema = preg_replace('/--(.*?);/', '', $schema);
    $schema = preg_replace('/[\r\n]/', ' ', $schema);
    $schema = trim($schema, ' ;');
    foreach (explode(';', $schema) as $stm) {
        $pdo->exec($stm);
    }
    unset($pdo);
}

$contents = file_get_contents($confpath);
if(getenv('AUTH_METHOD') == "ldap") {
    $config['PLUGINS'] = 'auth_ldap, note';
    $contents .= "define('LDAP_AUTH_SERVER_URI', '" . env("LDAP_AUTH_SERVER_URI", "ldap://ldap") . "');\n";
    $contents .= "define('LDAP_AUTH_USETLS', " . env("LDAP_AUTH_USETLS", "FALSE") . "); \n";
    $contents .= "define('LDAP_AUTH_ALLOW_UNTRUSTED_CERT', " . env("LDAP_AUTH_ALLOW_UNTRUSTED_CERT", "TRUE") . ");\n";
    $contents .= "define('LDAP_AUTH_BASEDN', '" . env("LDAP_AUTH_BASEDN") . "');\n";
    $contents .= "define('LDAP_AUTH_ANONYMOUSBEFOREBIND', " . env("LDAP_AUTH_ANONYMOUSBEFOREBIND", "FALSE") . ");\n";
    // ??? will be replaced with the entered username(escaped) at login 
    $contents .= "define('LDAP_AUTH_SEARCHFILTER', '" .env("LDAP_AUTH_SEARCHFILTER", "(&(objectClass=user)(sAMAccountName=???))") . "');\n";
    $contents .= "define('LDAP_AUTH_BINDDN', '" . env("LDAP_AUTH_BINDDN") . "');\n";
    $contents .= "define('LDAP_AUTH_BINDPW', '" . env("LDAP_AUTH_BINDPW") . "');\n";
    $contents .= "define('LDAP_AUTH_LOGIN_ATTRIB', '" . env("LDAP_AUTH_LOGIN_ATTRIB", "sAMAccountName") . "');\n";
    $contents .= "define('LDAP_AUTH_LOG_ATTEMPTS', " . env("LDAP_AUTH_LOG_ATTEMPTS", "FALSE") . ");\n";
    $contents .= "define('LDAP_AUTH_DEBUG', " . env("LDAP_AUTH_DEBUG", "FALSE") . ");\n";
}
foreach ($config as $name => $value) {
    $contents = preg_replace('/(define\s*\(\'' . $name . '\',\s*)(.*)(\);)/', '$1"' . $value . '"$3', $contents);
}

file_put_contents($confpath, $contents);

function env($name, $default = null)
{
    $v = getenv($name) ?: $default;
    
    if ($v === null) {
        error('The env ' . $name . ' does not exist');
    }
    
    return $v;
}

function error($text)
{
    echo 'Error: ' . $text . PHP_EOL;
    exit(1);
}

function dbconnect($config)
{
    $map = array('host' => 'HOST', 'port' => 'PORT', 'dbname' => 'NAME');
    $dsn = $config['DB_TYPE'] . ':';
    foreach ($map as $d => $h) {
        if (isset($config['DB_' . $h])) {
            $dsn .= $d . '=' . $config['DB_' . $h] . ';';
        }
    }
    $pdo = new \PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function dbcheck($config)
{
    try {
        dbconnect($config);
        return true;
    }
    catch (PDOException $e) {
        return false;
    }
}

