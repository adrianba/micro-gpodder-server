<?php

use KD2\Test;
use KD2\HTTP;
use KD2\HTMLDocument;

$framework = getenv('KD2FW_ROOT') ?: __DIR__ . '/../../kd2fw';
require $framework . '/src/lib/KD2/Test.php';
require $framework . '/src/lib/KD2/HTTP.php';
require $framework . '/src/lib/KD2/HTMLDocument.php';

$socket = stream_socket_server('tcp://127.0.0.1:0');
$server = stream_socket_get_name($socket, false);
fclose($socket);
$url = 'http://' . $server;

$data_root = sys_get_temp_dir() . '/opodsync-test-' . bin2hex(random_bytes(8));
mkdir($data_root);

$root = realpath(__DIR__ . '/../server');
$environment = array_replace(getenv(), [
	'DATA_ROOT' => $data_root,
	'BASE_URL' => $url . '/',
]);
unset($environment['PHP_CLI_SERVER_WORKERS']);
$process = proc_open(
	[PHP_BINARY, '-S', $server, '-d', 'variables_order=EGPCS', '-t', $root, $root . '/index.php'],
	[0 => ['file', '/dev/null', 'r'], 1 => ['file', $data_root . '/server.log', 'a'], 2 => ['file', $data_root . '/server.log', 'a']],
	$pipes,
	null,
	$environment
);

pcntl_async_signals(true);
pcntl_signal(SIGINT, function() {
	throw new RuntimeException('Tests interrupted');
});
pcntl_signal(SIGTERM, function() {
	throw new RuntimeException('Tests terminated');
});

$http = new HTTP;
$http->url_prefix = $url;
$http->http_options['timeout'] = 2;
$list = glob(__DIR__ . '/tests/*.php');
natcasesort($list);

try {
	$ready = false;
	for ($i = 0; $i < 100; $i++) {
		$connection = @stream_socket_client('tcp://' . $server, $errno, $error, 0.1);
		if ($connection) {
			fclose($connection);
			$ready = true;
			break;
		}
		if (!proc_get_status($process)['running']) {
			break;
		}
		usleep(50000);
	}
	Test::assert($ready, 'Test server failed to start');

	foreach ($list as $file) {
		echo "$file\n";
		require $file;
	}
	echo "All tests passed\n";
}
catch (Throwable $e) {
	fwrite(STDERR, file_get_contents($data_root . '/server.log'));
	throw $e;
}
finally {
	proc_terminate($process);
	proc_close($process);
	removeTestDirectory($data_root);
}

function dom(string $html) {
	$doc = new HTMLDocument;
	$doc->loadHTML($html);
	return $doc;
}

function removeTestDirectory(string $path): void {
	foreach (new FilesystemIterator($path) as $entry) {
		if ($entry->isDir() && !$entry->isLink()) {
			removeTestDirectory($entry->getPathname());
		}
		else {
			unlink($entry->getPathname());
		}
	}
	rmdir($path);
}

function runPHP(string $code, array $environment): array {
	$process = proc_open(
		[PHP_BINARY, '-r', $code],
		[0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
		$pipes,
		null,
		$environment
	);
	$output = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	return [proc_close($process), $output];
}
