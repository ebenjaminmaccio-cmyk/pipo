<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * WhatsApp: los mensajes entran por dos vías
 *  1) Webhook: tu CRM (o la API oficial de WhatsApp Cloud) hace POST a /webhook/whatsapp?token=...
 *  2) Importación manual del chat exportado (.txt) desde el celular.
 */
final class WhatsAppService
{
    /**
     * Acepta el formato de WhatsApp Cloud API de Meta y un formato genérico simple que
     * casi cualquier CRM puede configurar:
     *   {"chat":"Juan Pérez","sender":"Juan Pérez","body":"Hola","timestamp":1731700000,"direction":"in","id":"abc"}
     * o {"messages":[ ...lo mismo... ]}
     */
    public static function ingestWebhook(int $userId, array $payload): int
    {
        $items = [];

        // Formato Meta / WhatsApp Cloud API
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $names = [];
                foreach ($value['contacts'] ?? [] as $c) {
                    $names[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? ($c['wa_id'] ?? '');
                }
                foreach ($value['messages'] ?? [] as $m) {
                    $from = (string) ($m['from'] ?? '');
                    $items[] = [
                        'source' => 'cloud_api', 'id' => $m['id'] ?? null, 'chat' => $names[$from] ?? $from,
                        'sender' => $names[$from] ?? $from, 'direction' => 'in',
                        'body' => $m['text']['body'] ?? ('[' . ($m['type'] ?? 'mensaje') . ']'),
                        'timestamp' => $m['timestamp'] ?? time(),
                    ];
                }
            }
        }

        // Formato genérico (CRM)
        $generic = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : (isset($payload['body']) || isset($payload['text']) || isset($payload['message']) ? [$payload] : []);
        foreach ($generic as $m) {
            if (!is_array($m)) {
                continue;
            }
            $items[] = [
                'source' => 'crm',
                'id' => $m['id'] ?? $m['message_id'] ?? null,
                'chat' => $m['chat'] ?? $m['contact'] ?? $m['contact_name'] ?? $m['phone'] ?? $m['from'] ?? 'Desconocido',
                'sender' => $m['sender'] ?? $m['from_name'] ?? $m['from'] ?? $m['contact'] ?? 'Desconocido',
                'direction' => in_array($m['direction'] ?? 'in', ['out', 'outbound', 'sent'], true) ? 'out' : 'in',
                'body' => $m['body'] ?? $m['text'] ?? $m['message'] ?? '',
                'timestamp' => $m['timestamp'] ?? $m['date'] ?? $m['created_at'] ?? time(),
            ];
        }

        $n = 0;
        foreach ($items as $it) {
            $body = trim((string) (is_array($it['body']) ? ($it['body']['body'] ?? json_encode($it['body'])) : $it['body']));
            if ($body === '') {
                continue;
            }
            $ts = is_numeric($it['timestamp']) ? (int) $it['timestamp'] : (strtotime((string) $it['timestamp']) ?: time());
            if ($ts > 20_000_000_000) {
                $ts = intdiv($ts, 1000); // milisegundos
            }
            $n += self::insert($userId, $it['source'], $it['id'] !== null ? (string) $it['id'] : null, (string) $it['chat'], (string) $it['sender'], $it['direction'], $body, gmdate('Y-m-d H:i:s', $ts));
        }
        return $n;
    }

    /**
     * Importa un chat exportado de WhatsApp ("Exportar chat" -> "Sin archivos").
     * Soporta formatos Android ("16/11/26, 14:05 - Juan: Hola") e iPhone ("[16/11/26, 14:05:33] Juan: Hola").
     */
    public static function importExport(int $userId, string $chatName, string $text, string $tz, ?string $myName = null): int
    {
        $text = str_replace(["\r\n", "\u{200E}", "\u{202F}"], ["\n", '', ' '], $text);
        $re = '/^\[?(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4}),?\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([ap]\.?\s?m\.?)?\]?\s*(?:-\s*)?([^:]{1,80}):\s(.*)$/iu';
        $current = null;
        $rows = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match($re, $line, $m)) {
                if ($current) {
                    $rows[] = $current;
                }
                [$all, $d, $mo, $y, $h, $mi, $s, $ampm, $sender, $body] = $m + array_fill(0, 10, '');
                $y = strlen($y) === 2 ? '20' . $y : $y;
                $h = (int) $h;
                if ($ampm !== '') {
                    $pm = str_starts_with(strtolower($ampm), 'p');
                    $h = $h % 12 + ($pm ? 12 : 0);
                }
                $local = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $s !== '' ? $s : 0);
                $current = ['sender' => trim($sender), 'body' => $body, 'at' => Time::toUtc($local, $tz) ?? Time::nowUtc()];
            } elseif ($current && trim($line) !== '') {
                $current['body'] .= "\n" . $line;
            }
        }
        if ($current) {
            $rows[] = $current;
        }
        $n = 0;
        foreach ($rows as $r) {
            $direction = $myName !== null && $myName !== '' && mb_strtolower($r['sender']) === mb_strtolower($myName) ? 'out' : 'in';
            $remoteId = hash('sha256', $chatName . '|' . $r['at'] . '|' . $r['sender'] . '|' . $r['body']);
            $n += self::insert($userId, 'import', $remoteId, $chatName, $r['sender'], $direction, $r['body'], $r['at']);
        }
        return $n;
    }

    private static function insert(int $userId, string $source, ?string $remoteId, string $chat, string $sender, string $direction, string $body, string $sentAt): int
    {
        $st = Database::run(
            'INSERT IGNORE INTO whatsapp_messages (user_id, source, remote_id, chat, sender, direction, body, sent_at) VALUES (?,?,?,?,?,?,?,?)',
            [$userId, $source, $remoteId, mb_substr($chat, 0, 255), mb_substr($sender, 0, 255), $direction, mb_substr($body, 0, 20000), $sentAt]
        );
        return $st->rowCount();
    }

    /** @return list<array<string,mixed>> */
    public static function chats(int $userId): array
    {
        return Database::all(
            'SELECT chat, COUNT(*) total, MAX(sent_at) last_at, SUM(sent_at > UTC_TIMESTAMP() - INTERVAL 1 DAY) last_24h FROM whatsapp_messages WHERE user_id = ? GROUP BY chat ORDER BY last_at DESC LIMIT 200',
            [$userId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function messages(int $userId, ?string $chat, int $hours = 24, int $limit = 400): array
    {
        $sql = 'SELECT chat, sender, direction, body, sent_at FROM whatsapp_messages WHERE user_id = ? AND sent_at > UTC_TIMESTAMP() - INTERVAL ? HOUR';
        $params = [$userId, max(1, min($hours, 24 * 90))];
        if ($chat !== null && $chat !== '') {
            $sql .= ' AND chat LIKE ?';
            $params[] = '%' . addcslashes($chat, '%_\\') . '%';
        }
        $sql .= ' ORDER BY sent_at DESC LIMIT ' . max(1, min($limit, 1000));
        return array_reverse(Database::all($sql, $params));
    }
}
