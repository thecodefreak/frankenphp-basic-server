<?php

declare(strict_types=1);

namespace App\Ai;

use App\Support\Db;
use App\Support\Secrets;

final readonly class ProviderRepository
{
    public function __construct(
        private Db $db,
        private Secrets $secrets,
    ) {
    }

    public function all(): array
    {
        return $this->db->select('SELECT * FROM ai_providers ORDER BY kind, is_default DESC, name');
    }

    public function ofKind(string $kind): array
    {
        return $this->db->select('SELECT * FROM ai_providers WHERE kind = ? ORDER BY is_default DESC, name', [$kind]);
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM ai_providers WHERE id = ?', [$id]);
    }

    /** @param array{name:string,kind:string,type:string,base_url:string,api_key:?string,model:string,price_input_per_mtok:float,price_output_per_mtok:float,price_per_image:float,is_default:bool} $input */
    public function create(array $input): int
    {
        $id = $this->db->insert('ai_providers', [
            'name' => $input['name'],
            'kind' => $input['kind'],
            'type' => $input['type'],
            'base_url' => $input['base_url'] !== '' ? $input['base_url'] : null,
            'api_key_enc' => $this->secrets->encrypt((string) $input['api_key']),
            'model' => $input['model'],
            'options_json' => '{}',
            'price_input_per_mtok' => $input['price_input_per_mtok'],
            'price_output_per_mtok' => $input['price_output_per_mtok'],
            'price_per_image' => $input['price_per_image'],
            'is_default' => $input['is_default'] ? 1 : 0,
            'created_at' => utc_string(now_utc()),
        ]);

        if ($input['is_default']) {
            $this->makeDefault($id, $input['kind']);
        }

        return $id;
    }

    public function update(int $id, array $input): void
    {
        $data = [
            'name' => $input['name'],
            'type' => $input['type'],
            'base_url' => $input['base_url'] !== '' ? $input['base_url'] : null,
            'model' => $input['model'],
            'price_input_per_mtok' => $input['price_input_per_mtok'],
            'price_output_per_mtok' => $input['price_output_per_mtok'],
            'price_per_image' => $input['price_per_image'],
        ];

        if (($input['api_key'] ?? '') !== '') {
            $data['api_key_enc'] = $this->secrets->encrypt((string) $input['api_key']);
        }

        $this->db->update('ai_providers', $id, $data);

        if ($input['is_default']) {
            $this->makeDefault($id, $input['kind']);
        }
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM ai_providers WHERE id = ?', [$id]);
    }

    private function makeDefault(int $id, string $kind): void
    {
        $this->db->execute('UPDATE ai_providers SET is_default = 0 WHERE kind = ?', [$kind]);
        $this->db->execute('UPDATE ai_providers SET is_default = 1 WHERE id = ?', [$id]);
    }
}
