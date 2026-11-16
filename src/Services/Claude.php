<?php
declare(strict_types=1);

namespace App\Services;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use App\Core\Config;
use App\Core\Database;

/**
 * Envoltorio del SDK oficial de Anthropic.
 * - Modelo por defecto: claude-opus-5-5 (configurable).
 * - Fallback del lado del servidor activado: si el modelo rechaza un pedido, la API
 *   reintenta automáticamente con el modelo de respaldo por defecto.
 */
final class Claude
{
    private static ?Client $client = null;

    public static function client(): Client
    {
        if (self::$client === null) {
            $key = (string) Config::get('anthropic.api_key', '');
            if ($key === '' || str_contains($key, 'CAMBIAR')) {
                throw new \RuntimeException('Falta configurar la API key de Anthropic en config/config.php.');
            }
            $baseUrl = (string) Config::get('anthropic.base_url', '');
            self::$client = $baseUrl !== '' ? new Client(apiKey: $key, baseUrl: $baseUrl) : new Client(apiKey: $key);
        }
        return self::$client;
    }

    public static function model(): string
    {
        return (string) Config::get('anthropic.model', 'claude-opus-5-5');
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $tools
     */
    public static function create(int $userId, string $feature, string $system, array $messages, array $tools = [], int $maxTokens = 16000): BetaMessage
    {
        $params = [
            'model'        => self::model(),
            'maxTokens'    => $maxTokens,
            'system'       => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            'messages'     => $messages,
            'outputConfig' => ['effort' => (string) Config::get('anthropic.effort', 'medium')],
            'fallbacks'    => 'default',
            'betas'        => ['server-side-fallback-2026-07-01'],
        ];
        if ($tools !== []) {
            $params['tools'] = $tools;
        }
        $response = self::client()->beta->messages->create(...$params);

        try {
            Database::insert('ai_usage', [
                'user_id' => $userId, 'feature' => $feature, 'model' => $response->model,
                'input_tokens' => $response->usage->inputTokens + (int) $response->usage->cacheReadInputTokens + (int) $response->usage->cacheCreationInputTokens,
                'output_tokens' => $response->usage->outputTokens,
            ]);
        } catch (\Throwable) {
        }
        return $response;
    }

    public static function text(BetaMessage $m): string
    {
        $out = [];
        foreach ($m->content as $block) {
            if ($block->type === 'text') {
                $out[] = $block->text;
            }
        }
        return trim(implode("\n\n", $out));
    }

    /** Un pedido simple sin herramientas (resúmenes). */
    public static function complete(int $userId, string $feature, string $system, string $prompt, int $maxTokens = 8000): string
    {
        $r = self::create($userId, $feature, $system, [['role' => 'user', 'content' => $prompt]], [], $maxTokens);
        if ($r->stopReason === 'refusal') {
            return 'No pude generar este resumen.';
        }
        return self::text($r);
    }
}
