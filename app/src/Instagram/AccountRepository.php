<?php

declare(strict_types=1);

namespace App\Instagram;

use App\Support\Db;
use App\Support\Secrets;

final readonly class AccountRepository
{
    public function __construct(
        private Db $db,
        private Secrets $secrets,
    ) {
    }

    public function all(): array
    {
        return $this->db->select('SELECT * FROM instagram_accounts ORDER BY name');
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM instagram_accounts WHERE id = ?', [$id]);
    }

    public function create(array $input): int
    {
        return $this->db->insert('instagram_accounts', [
            'name' => $input['name'],
            'login_kind' => $input['login_kind'],
            'ig_user_id' => $input['ig_user_id'],
            'page_id' => $input['page_id'] !== '' ? $input['page_id'] : null,
            'access_token_enc' => $this->secrets->encrypt($input['access_token']),
            'token_expires_at' => $input['login_kind'] === 'instagram' ? utc_string(now_utc()->modify('+60 days')) : null,
            'created_at' => utc_string(now_utc()),
        ]);
    }

    public function update(int $id, array $input): void
    {
        $data = [
            'name' => $input['name'],
            'login_kind' => $input['login_kind'],
            'ig_user_id' => $input['ig_user_id'],
            'page_id' => $input['page_id'] !== '' ? $input['page_id'] : null,
        ];

        if (($input['access_token'] ?? '') !== '') {
            $data['access_token_enc'] = $this->secrets->encrypt($input['access_token']);
            $data['token_expires_at'] = $input['login_kind'] === 'instagram' ? utc_string(now_utc()->modify('+60 days')) : null;
        }

        $this->db->update('instagram_accounts', $id, $data);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM instagram_accounts WHERE id = ?', [$id]);
    }

    public function saveRefreshedToken(int $id, string $token, string $expiresAt): void
    {
        $this->db->update('instagram_accounts', $id, [
            'access_token_enc' => $this->secrets->encrypt($token),
            'token_expires_at' => $expiresAt,
            'last_refreshed_at' => utc_string(now_utc()),
            'last_check_error' => null,
        ]);
    }

    public function recordError(int $id, string $error): void
    {
        $this->db->update('instagram_accounts', $id, ['last_check_error' => $error]);
    }

    /** Accounts whose Instagram Login token expires within $withinDays. Facebook Page tokens never appear here. */
    public function expiringWithin(int $withinDays): array
    {
        $threshold = utc_string(now_utc()->modify('+' . $withinDays . ' days'));

        return $this->db->select(
            "SELECT * FROM instagram_accounts WHERE login_kind = 'instagram' AND token_expires_at IS NOT NULL AND token_expires_at <= ?",
            [$threshold]
        );
    }
}
