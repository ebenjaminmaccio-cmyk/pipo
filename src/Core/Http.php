<?php
declare(strict_types=1);

namespace App\Core;

/** Cliente HTTP mínimo (cURL) para las APIs de Google y webhooks. */
final class Http
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string, json:array<string,mixed>|null}
     */
    public static function request(string $method, string $url, array $headers = [], string|array|null $body = null, int $timeout = 30): array
    {
        // Solo HTTPS. Excepción: localhost con app.debug (para desarrollo).
        $host = (string) parse_url($url, PHP_URL_HOST);
        $allowHttp = Config::get('app.debug') && in_array($host, ['127.0.0.1', 'localhost'], true);
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            $h[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $h,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROTOCOLS      => $allowHttp ? (CURLPROTO_HTTPS | CURLPROTO_HTTP) : CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new \RuntimeException('Error de red: ' . $err);
        }
        $json = json_decode((string) $resp, true);
        return ['status' => $status, 'body' => (string) $resp, 'json' => is_array($json) ? $json : null];
    }
}
