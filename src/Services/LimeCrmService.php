<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Audit, Database, Http};

/**
 * Conector con Lime CRM (crmlime.com): trae los mensajes de WhatsApp / Instagram / Messenger
 * de la empresa configurada usando la API de solo lectura `pipo_mensajes` (ver integraciones/lime-crm/).
 * Pipo guarda el último id recibido (cursor) y en cada sincronización pide solo lo nuevo.
 */
final class LimeCrmService
{
    private const PAGE = 500;
    private const MAX_PAGES = 20; // hasta 10.000 mensajes por corrida

    private const TIPOS = [
        'image' => '📷 Imagen', 'audio' => '🎤 Audio', 'video' => '🎬 Video', 'document' => '📎 Documento',
        'sticker' => 'Sticker', 'template' => 'Plantilla', 'llamada' => '📞 Llamada',
    ];
    private const CANALES = ['instagram' => 'Instagram', 'facebook' => 'Messenger'];

    /** Normaliza la URL base (ej. "crmlime.com" -> "https://crmlime.com"). */
    public static function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $url = rtrim((string) preg_replace('#/api\.php.*$#i', '', $url), '/');
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /** @return array<string,mixed> respuesta "data" de Lime */
    public static function fetch(string $baseUrl, string $token, int $sinceId, int $limit = self::PAGE): array
    {
        $url = $baseUrl . '/api.php?' . http_build_query(['action' => 'pipo_mensajes', 'desde_id' => $sinceId, 'limite' => $limit]);
        $r = Http::request('GET', $url, ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'], null, 30);
        if ($r['status'] === 401) {
            throw new \RuntimeException('Lime rechazó el token. Generá uno nuevo y volvé a conectar.');
        }
        if ($r['status'] !== 200 || !($r['json']['ok'] ?? false)) {
            throw new \RuntimeException('Lime respondió con error: ' . ($r['json']['error'] ?? ('HTTP ' . $r['status'])));
        }
        return (array) ($r['json']['data'] ?? []);
    }

    /** Sincroniza todas las conexiones con Lime de un usuario. Devuelve cuántos mensajes nuevos guardó. */
    public static function syncUser(int $userId): int
    {
        $n = 0;
        foreach (Database::all("SELECT * FROM integrations WHERE user_id = ? AND provider = 'whatsapp_crm'", [$userId]) as $integration) {
            try {
                $n += self::sync($integration);
            } catch (\Throwable $e) {
                Audit::log('lime_sync_error', ['error' => $e->getMessage()], $userId);
            }
        }
        return $n;
    }

    public static function sync(array $integration): int
    {
        $secret = Integrations::secret($integration);
        $settings = json_decode((string) ($integration['settings'] ?? '{}'), true) ?: [];
        $cursor = (int) ($settings['cursor'] ?? 0);
        $userId = (int) $integration['user_id'];
        $stored = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = self::fetch((string) $secret['url'], (string) $secret['token'], $cursor);
            foreach ((array) ($data['mensajes'] ?? []) as $m) {
                $stored += self::store($userId, (array) $m);
            }
            $next = (int) ($data['siguiente_desde_id'] ?? $cursor);
            if ($next > $cursor) {
                $cursor = $next;
                $settings['cursor'] = $cursor;
                $settings['last_sync'] = gmdate('Y-m-d H:i:s');
                Database::run('UPDATE integrations SET settings = ? WHERE id = ?', [json_encode($settings), $integration['id']]);
            }
            if (empty($data['hay_mas'])) {
                break;
            }
        }
        return $stored;
    }

    private static function store(int $userId, array $m): int
    {
        $contact = trim((string) ($m['contacto'] ?? '')) ?: (string) ($m['telefono'] ?? 'Sin nombre');
        $canal = (string) ($m['canal'] ?? 'whatsapp');
        $chat = $contact . (isset(self::CANALES[$canal]) ? ' · ' . self::CANALES[$canal] : '');

        $text = trim((string) ($m['texto'] ?? ''));
        $tipo = (string) ($m['tipo'] ?? 'text');
        if ($tipo !== 'text' && isset(self::TIPOS[$tipo])) {
            $text = '[' . self::TIPOS[$tipo] . ']' . ($text !== '' ? ' ' . $text : '');
        }
        if ($text === '') {
            return 0;
        }
        $out = ($m['direccion'] ?? 'in') === 'out';
        $sender = $out ? (($m['autor'] ?? '') === 'bot' ? 'Bot de Lime' : 'Equipo') : $contact;
        $ts = (int) ($m['timestamp'] ?? time());

        return Database::run(
            "INSERT IGNORE INTO whatsapp_messages (user_id, source, remote_id, chat, sender, direction, body, sent_at) VALUES (?, 'crm', ?, ?, ?, ?, ?, ?)",
            [$userId, 'lime:' . (int) ($m['id'] ?? 0), mb_substr($chat, 0, 255), mb_substr($sender, 0, 255), $out ? 'out' : 'in', mb_substr($text, 0, 20000), gmdate('Y-m-d H:i:s', $ts)]
        )->rowCount();
    }
}
