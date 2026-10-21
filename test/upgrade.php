<?php

use KD2\Test;
use OPodSync\DB;
use OPodSync\GPodder;

require getenv('KD2FW_ROOT') . '/src/lib/KD2/Test.php';
define('OPodSync\ROOT', realpath(__DIR__ . '/../server'));
define('OPodSync\SQLITE_JOURNAL_MODE', 'TRUNCATE');
define('OPodSync\HTTP_SCHEME', 'http');
define('OPodSync\DB_FILE', getenv('TEST_DATA_ROOT') . '/upgrade.sqlite');
require OPodSync\ROOT . '/lib/OPodSync/DB.php';
require OPodSync\ROOT . '/lib/OPodSync/GPodder.php';

set_error_handler(static function ($severity, $message, $file, $line) {
	if (error_reporting() & $severity) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}
});

$old = new SQLite3(OPodSync\DB_FILE);
$old->exec(file_get_contents(__DIR__ . '/fixtures/schema_20240428.sql'));
$hash = password_hash('migration-test-password', PASSWORD_DEFAULT);
$statement = $old->prepare('INSERT INTO users VALUES (1, :name, :password)');
$statement->bindValue(':name', 'upgrade_user');
$statement->bindValue(':password', $hash);
$statement->execute();
$old->exec("INSERT INTO feeds (id, feed_url, last_fetch) VALUES (1, 'https://example.test/feed', 1);
INSERT INTO episodes (id, feed, media_url) VALUES (1, 1, 'https://example.test/audio');
INSERT INTO devices VALUES (1, 1, 'phone', 'Phone', '{\"caption\":\"Phone\"}');
INSERT INTO subscriptions VALUES (1, 1, 1, 'https://example.test/feed', 0, 1, '{}');
INSERT INTO episodes_actions VALUES (1, 1, 1, 1, 1, 'https://example.test/audio', 1, 'play', '{\"position\":42}');");
$tables = ['users', 'devices', 'subscriptions', 'feeds', 'episodes', 'episodes_actions'];
$before = [];
foreach ($tables as $table) {
	$before[$table] = $old->querySingle('SELECT * FROM ' . $table, true);
}
Test::equals(20240428, $old->querySingle('PRAGMA user_version'));
$old->close();

session_name('sessionid');
session_start();
$_SESSION['user'] = (object) ['id' => 1];
$db = DB::getInstance();
Test::equals(PHP_SESSION_NONE, session_status(), 'Upgrade must invalidate the active session');
Test::equals(20260911, $db->querySingle('PRAGMA user_version'));
foreach ($before as $table => $row) {
	Test::equals(1, $db->querySingle('SELECT COUNT(*) FROM ' . $table));
	Test::equals($row, array_intersect_key($db->querySingle('SELECT * FROM ' . $table, true), $row));
}
Test::assert(password_verify('migration-test-password', $db->querySingle('SELECT password FROM users')));
Test::equals(null, $db->querySingle('SELECT token FROM users'));
Test::equals(null, $db->querySingle('SELECT external_user_id FROM users'));
Test::equals('ok', $db->querySingle('PRAGMA integrity_check'));
Test::equals(null, $db->querySingle('PRAGMA foreign_key_check'));

$fresh = new DB(getenv('TEST_DATA_ROOT') . '/fresh.sqlite');
$indexes = 'SELECT name, tbl_name FROM sqlite_master WHERE type = \'index\' ORDER BY name';
Test::equals($fresh->all($indexes), $db->all($indexes), 'Fresh and upgraded indexes must match');
foreach ($tables as $table) {
	Test::equals($fresh->all('PRAGMA table_info(' . $table . ')'), $db->all('PRAGMA table_info(' . $table . ')'));
}
$again = new DB(OPodSync\DB_FILE);
Test::equals(20260911, $again->querySingle('PRAGMA user_version'));
foreach ($before as $table => $row) {
	Test::equals($row, array_intersect_key($again->querySingle('SELECT * FROM ' . $table, true), $row));
}
Test::equals($fresh->all($indexes), $again->all($indexes));

$gpodder = new GPodder;
$gpodder->user = $db->firstRow('SELECT * FROM users WHERE id = 1');
Test::equals(null, $gpodder->getUserToken());
$legacy = 'upgrade_user__' . substr(sha1($hash), 0, 10);
Test::assert(!$gpodder->validateToken($legacy), 'Legacy desktop tokens must be replaced');
$gpodder->user = $db->firstRow('SELECT * FROM users WHERE id = 1');
$gpodder->enableToken();
$token = $gpodder->getUserToken();
Test::assert($gpodder->validateToken($token));
$gpodder->disableToken();
Test::assert(!$gpodder->validateToken($token));
