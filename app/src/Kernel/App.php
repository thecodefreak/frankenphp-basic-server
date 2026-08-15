<?php

declare(strict_types=1);

namespace App\Kernel;

use App\Ai\ProviderFactory;
use App\Ai\ProviderRepository;
use App\Content\ImageStore;
use App\Content\PostGenerator;
use App\Content\PostRepository;
use App\Content\TemplateRepository;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\ErrorMiddleware;
use App\Instagram\AccountRepository;
use App\Instagram\InstagramClient;
use App\Notify\Webhook;
use App\Scheduling\Scheduler;
use App\Support\Db;
use App\Support\Http;
use App\Support\Migrator;
use App\Support\Secrets;
use App\Support\Settings;
use App\Usage\UsageRepository;
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

        $this->set(Webhook::class, static fn (self $c): Webhook => new Webhook(
            $c->get(Http::class),
            $c->settings(),
        ));

        $this->set(SettingsController::class, static fn (self $c): SettingsController => new SettingsController(
            $c->get(View::class),
            $c->settings(),
            $c->get(Webhook::class),
        ));

        $this->set(ProviderRepository::class, static fn (self $c): ProviderRepository => new ProviderRepository(
            $c->db(),
            $c->get(Secrets::class),
        ));

        $this->set(ProviderFactory::class, static fn (self $c): ProviderFactory => new ProviderFactory(
            $c->get(Http::class),
            $c->get(Secrets::class),
        ));

        $this->set(ProviderController::class, static fn (self $c): ProviderController => new ProviderController(
            $c->get(View::class),
            $c->get(ProviderRepository::class),
            $c->get(ProviderFactory::class),
        ));

        $this->set(TemplateRepository::class, static fn (self $c): TemplateRepository => new TemplateRepository($c->db()));

        $this->set(TemplateController::class, static fn (self $c): TemplateController => new TemplateController(
            $c->get(View::class),
            $c->get(TemplateRepository::class),
            $c->get(ProviderRepository::class),
            $c->db(),
            $c->settings(),
        ));

        $this->set(ImageStore::class, static fn (self $c): ImageStore => new ImageStore(
            $c->settings(),
            app_path('public/storage/images'),
        ));

        $this->set(PostRepository::class, static fn (self $c): PostRepository => new PostRepository($c->db()));

        $this->set(UsageRepository::class, static fn (self $c): UsageRepository => new UsageRepository($c->db()));

        $this->set(PostGenerator::class, static fn (self $c): PostGenerator => new PostGenerator(
            $c->get(ProviderFactory::class),
            $c->get(ProviderRepository::class),
            $c->get(ImageStore::class),
            $c->get(PostRepository::class),
            $c->get(UsageRepository::class),
        ));

        $this->set(PostController::class, static fn (self $c): PostController => new PostController(
            $c->get(View::class),
            $c->get(PostRepository::class),
            $c->get(TemplateRepository::class),
            $c->get(PostGenerator::class),
            $c->get(UsageRepository::class),
        ));

        $this->set(AccountRepository::class, static fn (self $c): AccountRepository => new AccountRepository(
            $c->db(),
            $c->get(Secrets::class),
        ));

        $this->set(InstagramClient::class, static fn (self $c): InstagramClient => new InstagramClient(
            $c->get(Http::class),
            $c->get(Secrets::class),
        ));

        $this->set(AccountController::class, static fn (self $c): AccountController => new AccountController(
            $c->get(View::class),
            $c->get(AccountRepository::class),
            $c->get(InstagramClient::class),
        ));

        $this->set(Scheduler::class, static fn (self $c): Scheduler => new Scheduler(
            $c->get(TemplateRepository::class),
            $c->get(PostRepository::class),
            $c->get(PostGenerator::class),
            $c->get(AccountRepository::class),
            $c->get(InstagramClient::class),
            $c->get(Webhook::class),
            $c->settings(),
            $c->get(ImageStore::class),
        ));
    }

    private function registerRoutes(Slim $slim): void
    {
        $slim->get('/health', fn ($request, $response) => json_out($response, ['status' => 'ok']));

        $slim->get('/', DashboardController::class . ':index')->setName('dashboard');

        $slim->get('/settings', SettingsController::class . ':edit');
        $slim->post('/settings', SettingsController::class . ':update');
        $slim->post('/settings/webhook-test', SettingsController::class . ':webhookTest');

        $slim->get('/providers', ProviderController::class . ':index');
        $slim->get('/providers/new', ProviderController::class . ':create');
        $slim->post('/providers', ProviderController::class . ':store');
        $slim->get('/providers/{id}/edit', ProviderController::class . ':edit');
        $slim->post('/providers/{id}', ProviderController::class . ':update');
        $slim->post('/providers/{id}/delete', ProviderController::class . ':delete');
        $slim->post('/providers/{id}/test', ProviderController::class . ':test');

        $slim->get('/templates', TemplateController::class . ':index');
        $slim->get('/templates/new', TemplateController::class . ':create');
        $slim->post('/templates', TemplateController::class . ':store');
        $slim->post('/templates/preview-slots', TemplateController::class . ':previewSlots');
        $slim->get('/templates/{id}/edit', TemplateController::class . ':edit');
        $slim->post('/templates/{id}', TemplateController::class . ':update');
        $slim->post('/templates/{id}/delete', TemplateController::class . ':delete');

        $slim->get('/posts', PostController::class . ':index');
        $slim->post('/posts/generate', PostController::class . ':generateNow');
        $slim->get('/posts/{id}', PostController::class . ':show');
        $slim->post('/posts/{id}', PostController::class . ':update');
        $slim->post('/posts/{id}/retry', PostController::class . ':retry');
        $slim->post('/posts/{id}/publish-now', PostController::class . ':publishNow');
        $slim->post('/posts/{id}/cancel', PostController::class . ':cancel');

        $slim->get('/accounts', AccountController::class . ':index');
        $slim->get('/accounts/new', AccountController::class . ':create');
        $slim->post('/accounts', AccountController::class . ':store');
        $slim->get('/accounts/{id}/edit', AccountController::class . ':edit');
        $slim->post('/accounts/{id}', AccountController::class . ':update');
        $slim->post('/accounts/{id}/delete', AccountController::class . ':delete');
        $slim->post('/accounts/{id}/test', AccountController::class . ':test');
    }
}
