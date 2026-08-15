<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

(new App\Kernel\App())->slim()->run();
