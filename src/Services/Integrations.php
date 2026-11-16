<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

/** Guarda/lee credenciales cifradas de servicios externos. */
final class Integrations
{
    /** @return list<array<string,mixed>> */
    public static function forUser(int $userId, ?string $provider = null): array
    {
        if ($provider === null) {
            return Database::all('SELECT id, provider, label, settings, created_at FROM integrations WHERE user_id = ? ORDER BY id', [$userId]);
        }
        return Database::all('SELECT id, provider, label, settings, created_at FROM integrations WHERE user_id = ? AND provider = ? ORDER BY id', [$userId, $provider]);
    }

    /** @return array<string,mixed>|null */
    public static function get(int $userId, int $id): ?array
    {
        return Database::one('SELECT * FROM integrations WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** @return array<string,mixed> */
    public static function secret(array $integration): array
    {
        return Crypto::decryptJson((string) $integration['secret']);
    }

    /** @param array<string,mixed> $secret @param array<string,mixed> $settings */
    public static function save(int $userId, string $provider, string $label, array $secret, array $settings = []): int
    {
        $existing = Database::one('SELECT id FROM integrations WHERE user_id = ? AND provider = ? AND label = ?', [$userId, $provider, $label]);
        if ($existing) {
            Database::run('UPDATE integrations SET secret = ?, settings = ? WHERE id = ?', [
                Crypto::encryptJson($secret), json_encode($settings), $existing['id'],
            ]);
            return (int) $existing['id'];
        }
        return Database::insert('integrations', [
            'user_id' => $userId, 'provider' => $provider, 'label' => $label,
            'secret' => Crypto::encryptJson($secret), 'settings' => json_encode($settings),
        ]);
    }

    /** @param array<string,mixed> $secret */
    public static function updateSecret(int $id, array $secret): void
    {
        Database::run('UPDATE integrations SET secret = ? WHERE id = ?', [Crypto::encryptJson($secret), $id]);
    }

    public static function delete(int $userId, int $id): void
    {
        Database::run('DELETE FROM integrations WHERE id = ? AND user_id = ?', [$id, $userId]);
    }
}
