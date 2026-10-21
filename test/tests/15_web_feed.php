<?php

use KD2\Test;

$socket = stream_socket_server('tcp://127.0.0.1:0');
$fixture_server = stream_socket_get_name($socket, false);
fclose($socket);
$fixture = proc_open(
	[PHP_BINARY, '-S', $fixture_server, __DIR__ . '/../fixtures/feed.php'],
	[0 => ['file', '/dev/null', 'r'], 1 => ['file', $data_root . '/fixture.log', 'a'], 2 => ['file', $data_root . '/fixture.log', 'a']],
	$pipes,
	null,
	$environment
);
try {
	for ($i = 0; $i < 100; $i++) {
		$connection = @stream_socket_client('tcp://' . $fixture_server, $errno, $error, 0.1);
		if ($connection) {
			fclose($connection);
			break;
		}
		usleep(50000);
	}
	$feed_url = 'http://' . $fixture_server . '/feed.xml';
	$r = $http->POST('/subscriptions.php', ['feed_url' => $feed_url]);
	Test::equals(200, $r->status, $r);
	Test::assert(str_contains($r->body, 'Successfully subscribed'), $r->body);
	$db = new SQLite3($data_root . '/data.sqlite', SQLITE3_OPEN_READONLY);
	$id = $db->querySingle('SELECT id FROM subscriptions ORDER BY id DESC LIMIT 1');
	Test::equals(90, $db->querySingle('SELECT duration FROM episodes LIMIT 1'));
	$r = $http->GET('/feed.php?id=' . $id);
	Test::equals(200, $r->status, $r);
	Test::assert(str_contains($r->body, 'Browser fixture episode'), $r->body);
	Test::assert(str_contains($r->body, 'id="pb-audio"'), $r->body);
	$r = $http->GET('/player.js');
	Test::equals(200, $r->status, $r);

	$r = $http->GET('/register.php');
	Test::equals(200, $r->status, $r);
	Test::assert(str_contains($r->body, 'name="captcha"'), 'Registration should remain open');

	$r = $http->POST('/subscriptions.php', ['unsubscribe' => $id]);
	Test::equals(200, $r->status, $r);
	Test::assert(str_contains($r->body, 'Successfully unsubscribed'), $r->body);
	Test::equals(1, $db->querySingle('SELECT deleted FROM subscriptions WHERE id = ' . (int) $id));
	$db->close();
}
finally {
	proc_terminate($fixture);
	proc_close($fixture);
}
