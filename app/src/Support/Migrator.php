<?php

declare(strict_types=1);

namespace App\Support;

final class Migrator
{
    public function __construct(
        private readonly Db $db,
        private readonly string $directory,
    ) {
    }

    /** @return string[] names of migrations applied by this run */
    public function run(): array
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

        $applied = array_column($this->db->select('SELECT name FROM migrations'), 'name');
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files);

        $ran = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('Unable to read migration ' . $name);
            }

            $pdo = $this->db->pdo();
            $pdo->beginTransaction();

            try {
                $pdo->exec($sql);
                $this->db->insert('migrations', ['name' => $name, 'applied_at' => utc_string(now_utc())]);
                $pdo->commit();
            } catch (\Throwable $exception) {
                $pdo->rollBack();

                throw new \RuntimeException('Migration ' . $name . ' failed: ' . $exception->getMessage(), 0, $exception);
            }

            $ran[] = $name;
        }

        return $ran;
    }
}
