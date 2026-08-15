<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\Db;

final readonly class TemplateRepository
{
    public function __construct(private Db $db)
    {
    }

    public function all(): array
    {
        return $this->db->select(
            "SELECT t.*, tp.name AS text_provider_name, ip.name AS image_provider_name, ia.name AS account_name
             FROM templates t
             LEFT JOIN ai_providers tp ON tp.id = t.text_provider_id
             LEFT JOIN ai_providers ip ON ip.id = t.image_provider_id
             LEFT JOIN instagram_accounts ia ON ia.id = t.instagram_account_id
             ORDER BY t.is_active DESC, t.name"
        );
    }

    public function active(): array
    {
        return $this->db->select('SELECT * FROM templates WHERE is_active = 1');
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM templates WHERE id = ?', [$id]);
    }

    public function create(array $input): int
    {
        $id = $this->db->insert('templates', [
            ...$this->columns($input),
            'created_at' => utc_string(now_utc()),
        ]);

        if ($input['is_default']) {
            $this->makeDefault($id);
        }

        return $id;
    }

    public function update(int $id, array $input): void
    {
        $this->db->update('templates', $id, $this->columns($input));

        if ($input['is_default']) {
            $this->makeDefault($id);
        }
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM templates WHERE id = ?', [$id]);
    }

    private function columns(array $input): array
    {
        return [
            'name' => $input['name'],
            'subject' => $input['subject'],
            'description' => $input['description'],
            'style_prompt' => $input['style_prompt'],
            'caption_rules' => $input['caption_rules'],
            'text_provider_id' => $input['text_provider_id'],
            'image_provider_id' => $input['image_provider_id'],
            'image_count' => $input['image_count'],
            'instagram_account_id' => $input['instagram_account_id'],
            'timezone' => $input['timezone'],
            'schedule_json' => $input['schedule_json'],
            'is_active' => $input['is_active'] ? 1 : 0,
        ];
    }

    private function makeDefault(int $id): void
    {
        $this->db->execute('UPDATE templates SET is_default = 0');
        $this->db->execute('UPDATE templates SET is_default = 1 WHERE id = ?', [$id]);
    }
}
