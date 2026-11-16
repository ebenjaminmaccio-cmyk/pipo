<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;

/**
 * OAuth 2.0 de Google + llamadas autenticadas a Gmail y Calendar.
 * Un único consentimiento cubre las dos APIs.
 */
final class GoogleClient
{
    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.modify',   // leer, marcar como leído y enviar
        'https://www.googleapis.com/auth/calendar.events', // leer y crear eventos
    ];

    public static function isConfigured(): bool
    {
        return Config::get('google.client_id') !== '' && Config::get('google.client_secret') !== '';
    }

    public static function redirectUri(): string
    {
        return rtrim((string) Config::get('app.url'), '/') . '/google/callback';
    }

    public static function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => Config::get('google.client_id'),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
            'include_granted_scopes' => 'true',
        ]);
    }

    /** @return array<string,mixed> tokens */
    public static function exchangeCode(string $code): array
    {
        $r = Http::request('POST', 'https://oauth2.googleapis.com/token', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query([
            'code'          => $code,
            'client_id'     => Config::get('google.client_id'),
            'client_secret' => Config::get('google.client_secret'),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ]));
        if ($r['status'] !== 200 || !isset($r['json']['access_token'])) {
            throw new \RuntimeException('Google rechazó la autorización.');
        }
        $t = $r['json'];
        $t['expires_at'] = time() + (int) ($t['expires_in'] ?? 3600) - 60;
        return $t;
    }

    public static function emailFromIdToken(string $idToken): string
    {
        $parts = explode('.', $idToken);
        $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
        return (string) ($payload['email'] ?? 'cuenta-google');
    }

    /** Devuelve un access token válido, refrescándolo si hace falta. */
    public static function accessToken(array $integration): string
    {
        $secret = Integrations::secret($integration);
        if (($secret['expires_at'] ?? 0) > time()) {
            return (string) $secret['access_token'];
        }
        if (empty($secret['refresh_token'])) {
            throw new \RuntimeException('La conexión con Google expiró. Volvé a conectarla en Configuración.');
        }
        $r = Http::request('POST', 'https://oauth2.googleapis.com/token', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query([
            'client_id'     => Config::get('google.client_id'),
            'client_secret' => Config::get('google.client_secret'),
            'refresh_token' => $secret['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]));
        if ($r['status'] !== 200 || !isset($r['json']['access_token'])) {
            throw new \RuntimeException('No se pudo renovar el acceso a Google. Volvé a conectar la cuenta.');
        }
        $secret['access_token'] = $r['json']['access_token'];
        $secret['expires_at'] = time() + (int) ($r['json']['expires_in'] ?? 3600) - 60;
        Integrations::updateSecret((int) $integration['id'], $secret);
        return (string) $secret['access_token'];
    }

    /** @return array<string,mixed> */
    public static function api(array $integration, string $method, string $url, ?array $body = null): array
    {
        $r = Http::request($method, $url, ['Authorization' => 'Bearer ' . self::accessToken($integration)], $body);
        if ($r['status'] >= 400) {
            $msg = $r['json']['error']['message'] ?? ('HTTP ' . $r['status']);
            throw new \RuntimeException('Google API: ' . $msg);
        }
        return $r['json'] ?? [];
    }

    /** Primera cuenta de Google conectada del usuario (o null). */
    public static function primary(int $userId): ?array
    {
        return \App\Core\Database::one("SELECT * FROM integrations WHERE user_id = ? AND provider = 'google' ORDER BY id LIMIT 1", [$userId]);
    }
}
