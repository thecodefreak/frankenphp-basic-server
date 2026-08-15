<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Kernel\App;
use App\Scheduling\Scheduler;

$app = new App();
$app->migrate();

/** @var Scheduler $scheduler */
$scheduler = $app->get(Scheduler::class);

foreach ($scheduler->tick() as $line) {
    fwrite(STDOUT, '[' . utc_string(now_utc()) . '] ' . $line . PHP_EOL);
}
