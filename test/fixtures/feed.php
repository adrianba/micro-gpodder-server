<?php

$base = 'http://' . $_SERVER['HTTP_HOST'];
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/audio.wav') {
	$length = 90 * 8000 * 2;
	$audio = 'RIFF' . pack('V', $length + 36) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
		. 'data' . pack('V', $length) . str_repeat("\0", $length);
	header('Content-Type: audio/wav');
	header('Accept-Ranges: bytes');
	$start = 0;
	$end = strlen($audio) - 1;
	if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $match)) {
		$start = (int) $match[1];
		$end = $match[2] === '' ? $end : min($end, (int) $match[2]);
		http_response_code(206);
		header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, strlen($audio)));
	}
	header('Content-Length: ' . ($end - $start + 1));
	echo substr($audio, $start, $end - $start + 1);
	return;
}
header('Content-Type: application/rss+xml');
echo '<?xml version="1.0"?>';
?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
<channel>
<title>Local test feed</title>
<link><?= $base ?>/</link>
<description>Deterministic integration fixture</description>
<pubDate>Wed, 21 Oct 2026 12:00:00 GMT</pubDate>
<item>
<title>Browser fixture episode</title>
<link><?= $base ?>/episode</link>
<description>Local silent audio fixture</description>
<pubDate>Wed, 21 Oct 2026 12:00:00 GMT</pubDate>
<itunes:duration>00:01:30</itunes:duration>
<enclosure url="<?= $base ?>/audio.wav" type="audio/wav" length="1440044" />
</item>
</channel>
</rss>
