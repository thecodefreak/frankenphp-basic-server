<?php

declare(strict_types=1);

namespace App\Support;

final class Settings
{
    private ?array $cache = null;

    public function __construct(private readonly Db $db)
    {
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = array_column($this->db->select('SELECT key, value FROM settings'), 'value', 'key');
        }

        return $this->cache;
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, (string) $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute(
            'INSERT INTO settings (key, value) VALUES (:key, :value)
             ON CONFLICT (key) DO UPDATE SET value = excluded.value',
            ['key' => $key, 'value' => $value]
        );

        $this->cache = null;
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, (string) $value);
        }
    }

    public function publicBaseUrl(): string
    {
        return rtrim($this->get('public_base_url'), '/');
    }

    public function timezone(): string
    {
        return $this->get('default_timezone', 'UTC');
    }
}
