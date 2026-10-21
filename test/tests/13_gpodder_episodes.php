<?php

use KD2\HTTP;
use KD2\Test;

$data = json_decode(file_get_contents(__DIR__ . '/../episodes.json'), true);

$r = $http->POST('/api/2/episodes/demo.json', $data, HTTP::JSON);
Test::equals(200, $r->status, $r);

$r = $http->GET('/api/2/episodes/demo.json');
Test::equals(200, $r->status, $r);

$r = json_decode($r);
Test::assert(is_object($r));
Test::assert(isset($r->actions));
Test::assert(count($r->actions) === 2);

$data[0]['device'] = 'test-device';
$data[0]['position'] = 123;
$without_device = $data[0];
unset($without_device['device']);
$r = $http->POST('/api/2/episodes/demo.json', [$data[0], $without_device], HTTP::JSON);
Test::equals(200, $r->status, $r);

$db = new SQLite3($data_root . '/data.sqlite', SQLITE3_OPEN_READONLY);
$device = $db->querySingle('SELECT device FROM episodes_actions ORDER BY id DESC LIMIT 1 OFFSET 1');
Test::equals((int) $db->querySingle("SELECT id FROM devices WHERE deviceid = 'test-device'"), $device);
Test::equals(1, $db->querySingle('SELECT COUNT(*) FROM episodes_actions WHERE device IS NULL'));
$db->close();

$r = $http->GET('/api/2/episodes/demo.json');
Test::equals(200, $r->status, $r);
$actions = json_decode($r->body)->actions;
Test::equals(1, count(array_filter($actions, fn($action) =>
	($action->device ?? null) === 'test-device' && ($action->position ?? null) === 123)));
Test::equals(1, count(array_filter($actions, fn($action) => !isset($action->device))));
