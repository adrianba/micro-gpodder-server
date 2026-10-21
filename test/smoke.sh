#!/bin/bash
set -euo pipefail

IMAGE="${1:?Usage: bash test/smoke.sh IMAGE}"
NAME="opodsync-smoke-$$-$RANDOM"
VOLUME="$NAME-data"

cleanup() {
	docker rm -fv "$NAME" > /dev/null 2>&1 || true
	docker volume rm "$VOLUME" > /dev/null 2>&1 || true
}
trap cleanup EXIT

ready() {
	for ((i = 0; i < 100; i++)); do
		if curl --fail --silent "$URL/" > /dev/null; then
			return
		fi
		sleep 0.1
	done
	docker logs "$NAME"
	return 1
}

for DATA_PATH in /var/www/server/data /state; do
	docker volume create "$VOLUME" > /dev/null
	docker run --detach --rm --name "$NAME" \
		-p 127.0.0.1::8080 -v "$VOLUME:$DATA_PATH" \
		-e DATA_ROOT="$DATA_PATH" -e ERRORS_SHOW=false \
		"$IMAGE" > /dev/null
	URL="http://$(docker port "$NAME" 8080/tcp)"
	ready
	curl --fail --silent "$URL/style.css" > /dev/null
	curl --fail --silent "$URL/player.js" > /dev/null
	docker exec -e BASE_URL=http://localhost:8080/ "$NAME" php -r '
		if (is_dir("/var/www/test") || is_dir("/var/kd2fw")) {
			throw new RuntimeException("Test dependencies in production image");
		}
		if (!file_exists("/var/www/config.dist.php")) {
			throw new RuntimeException("Missing configuration example");
		}
		require "/var/www/server/_inc.php";
		$db = OPodSync\DB::getInstance();
		$st = $db->prepare("INSERT INTO users (name, password) VALUES (?, ?)");
		$st->bindValue(1, "smoke-user");
		$st->bindValue(2, password_hash("smoke-password", PASSWORD_DEFAULT));
		$st->execute();'
	curl --fail --silent -u smoke-user:smoke-password \
		-H 'Content-Type: application/json' \
		-d '[{"podcast":"https://example.test/feed","episode":"https://example.test/audio","action":"play","position":42}]' \
		"$URL/api/2/episodes/current.json" > /dev/null
	curl --fail --silent "$URL/register.php" | grep -q 'name="captcha"'
	docker exec "$NAME" php -r '
		file_put_contents(getenv("DATA_ROOT") . "/config.local.php",
			"<?php namespace OPodSync; const ENABLE_SUBSCRIPTIONS = false;");'
	docker restart "$NAME" > /dev/null
	URL="http://$(docker port "$NAME" 8080/tcp)"
	ready
	curl --fail --silent "$URL/register.php" | grep -q 'Subscriptions are disabled'
	curl --fail --silent -u smoke-user:smoke-password \
		"$URL/api/2/episodes/current.json" | grep -q '"position": 42'
	docker exec "$NAME" php -r '
		if (!is_dir(getenv("DATA_ROOT") . "/cache/templates")) {
			throw new RuntimeException("Template cache was not created");
		}
		$db = new SQLite3(getenv("DATA_ROOT") . "/data.sqlite");
		if ($db->querySingle("PRAGMA integrity_check") !== "ok") {
			throw new RuntimeException("Database integrity check failed");
		}'
	cleanup
done
echo "Production container and persistence checks passed"
