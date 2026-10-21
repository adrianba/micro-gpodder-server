<?php

use KD2\HTTP;
use KD2\Test;

$client = new HTTP;
$client->url_prefix = $url;
$r = $client->POST('/index.php/login/v2');
Test::equals(200, $r->status, $r);
$flow = json_decode($r->body);
$login_path = parse_url($flow->login, PHP_URL_PATH) . '?' . parse_url($flow->login, PHP_URL_QUERY);
$browser = new HTTP;
$browser->url_prefix = $url;
$r = $browser->POST($login_path, ['login' => 'demo', 'password' => 'demodemo']);
Test::equals(200, $r->status, $r);
Test::assert(str_contains($r->body, 'You are logged in'), $r->body);
$r = $client->POST(parse_url($flow->poll->endpoint, PHP_URL_PATH), ['token' => $flow->poll->token]);
Test::equals(200, $r->status, $r);
$credentials = json_decode($r->body);
Test::equals('demo', $credentials->loginName);

$endpoint = '/index.php/apps/gpoddersync/subscriptions';
foreach ([$credentials->appPassword, 'demodemo'] as $password) {
	$r = $client->GET($endpoint, ['Authorization' => 'Basic ' . base64_encode('demo:' . $password)]);
	Test::equals(200, $r->status, $r);
	Test::assert(is_array(json_decode($r->body)->add));
}
$r = $client->GET($endpoint, ['Authorization' => 'Basic ' . base64_encode('demo:invalid')]);
Test::equals(401, $r->status, $r);
