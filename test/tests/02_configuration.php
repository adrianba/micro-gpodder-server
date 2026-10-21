<?php

use KD2\Test;

$config_root = $data_root . '/configuration';
mkdir($config_root);
$bootstrap = var_export($root . '/_inc.php', true);
$code = 'namespace OPodSync; %s require ' . $bootstrap . ';
echo json_encode([BASE_URL, ENABLE_SUBSCRIPTIONS, HTTP_SCHEME]);';
$env = array_replace($environment, ['DATA_ROOT' => $config_root]);

[$status, $output] = runPHP(sprintf($code, ''), $env);
Test::equals(0, $status, $output);
Test::equals([$url . '/', true, 'http'], json_decode($output, true));

$env['BASE_URL'] = 'https://podcasts.example.test/podcasts/';
[$status, $output] = runPHP(sprintf($code, ''), $env);
Test::equals(0, $status, $output);
Test::equals($env['BASE_URL'], json_decode($output, true)[0]);

file_put_contents($config_root . '/config.local.php', '<?php namespace OPodSync;
const BASE_URL = "https://configured.example.test/sync/";
const ENABLE_SUBSCRIPTIONS = false;');
[$status, $output] = runPHP(sprintf($code, ''), $env);
Test::equals(0, $status, $output);
Test::equals(['https://configured.example.test/sync/', false, 'http'], json_decode($output, true));
unlink($config_root . '/config.local.php');

unset($env['BASE_URL']);
$setup = '$_SERVER["SERVER_NAME"] = "autodetected.example.test";
$_SERVER["SERVER_PORT"] = 443; $_SERVER["HTTPS"] = "on";';
[$status, $output] = runPHP(sprintf($code, $setup), $env);
Test::equals(0, $status, $output);
Test::equals(['https://autodetected.example.test/', true, 'https'], json_decode($output, true));

[$status, $output] = runPHP('try { require ' . $bootstrap . '; }
catch (LogicException $e) { echo $e->getMessage(); exit(1); }', $env);
Test::equals(1, $status, $output);
Test::assert(str_contains($output, 'please set BASE_URL'), $output);

$env['BASE_URL'] = $url . '/';
[$status, $output] = runPHP('require ' . var_export($root . '/index.php', true) . ';', $env);
Test::equals(0, $status, $output);
Test::assert(str_contains($output, 'Nothing to update'), $output);
