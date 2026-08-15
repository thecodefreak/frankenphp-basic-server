<?php

declare(strict_types=1);

namespace App\Kernel;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\ErrorMiddleware;
use App\Support\Db;
use App\Support\Http;
use App\Support\Migrator;
use App\Support\Secrets;
use App\Support\Settings;
use App\View;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Slim\App as Slim;
use Slim\Factory\AppFactory;

final class App implements ContainerInterface
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function __construct()
    {
        $this->registerServices();
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException('Service not registered: ' . $id);
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function db(): Db
    {
        return $this->get(Db::class);
    }

    public function settings(): Settings
    {
        return $this->get(Settings::class);
    }

    public function migrate(): array
    {
        return $this->get(Migrator::class)->run();
    }

    public function slim(): Slim
    {
        AppFactory::setContainer($this);
        $slim = AppFactory::create();

        $slim->addBodyParsingMiddleware();
        $slim->addRoutingMiddleware();
        $slim->add(new ErrorMiddleware($this->get(View::class)));

        $this->registerRoutes($slim);

        return $slim;
    }

    private function registerServices(): void
    {
        $this->set(Db::class, static fn (): Db => new Db(env('DB_PATH', '/data/app.sqlite')));

        $this->set(Migrator::class, static fn (self $c): Migrator => new Migrator($c->db(), app_path('migrations')));

        $this->set(Settings::class, static fn (self $c): Settings => new Settings($c->db()));

        $this->set(Secrets::class, static fn (): Secrets => new Secrets(env('APP_KEY')));

        $this->set(Http::class, static fn (): Http => new Http());

        $this->set(View::class, static fn (): View => new View(app_path('templates')));

        $this->set(DashboardController::class, static fn (self $c): DashboardController => new DashboardController(
            $c->get(View::class),
            $c->db(),
            $c->settings(),
        ));

        $this->set(SettingsController::class, static fn (self $c): SettingsController => new SettingsController(
            $c->get(View::class),
            $c->settings(),
        ));
    }

    private function registerRoutes(Slim $slim): void
    {
        $slim->get('/health', fn ($request, $response) => json_out($response, ['status' => 'ok']));

        $slim->get('/', DashboardController::class . ':index')->setName('dashboard');

        $slim->get('/settings', SettingsController::class . ':edit');
        $slim->post('/settings', SettingsController::class . ':update');
    }
}
