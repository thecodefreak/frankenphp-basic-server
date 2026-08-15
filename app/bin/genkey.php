<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

fwrite(STDOUT, App\Support\Secrets::generateKey() . PHP_EOL);
