<?php

use KD2\Test;

[$status, $output] = runPHP('require ' . var_export(__DIR__ . '/../upgrade.php', true) . ';',
	array_replace($environment, ['KD2FW_ROOT' => $framework, 'TEST_DATA_ROOT' => $data_root]));
Test::equals(0, $status, $output);
