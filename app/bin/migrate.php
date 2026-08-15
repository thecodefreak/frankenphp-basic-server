<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new App\Kernel\App();

foreach ($app->migrate() as $migration) {
    fwrite(STDOUT, 'Applied ' . $migration . PHP_EOL);
}
