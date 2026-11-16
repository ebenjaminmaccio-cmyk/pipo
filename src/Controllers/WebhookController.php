<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Audit, Database, Request};
use App\Services\WhatsAppService;

/**
 * Endpoint público para que tu CRM (o la API de WhatsApp Cloud) envíe los mensajes.
 * Se autentica con el token secreto del usuario (?token=... o cabecera X-Webhook-Token).
 */
final class WebhookController
{
    public static function whatsapp(): void
    {
        header('Content-Type: application/json');
        $token = (string) ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            http_response_code(401);
            exit('{"error":"unauthorized"}');
        }
        $user = Database::one('SELECT id FROM users WHERE webhook_token = ? AND is_active = 1', [$token]);
        if (!$user) {
            http_response_code(401);
            exit('{"error":"unauthorized"}');
        }

        // Verificación de webhook de Meta (WhatsApp Cloud API): usar el mismo token como "verify token"
        if (Request::method() === 'GET') {
            if (($_GET['hub_mode'] ?? '') === 'subscribe' && hash_equals($token, (string) ($_GET['hub_verify_token'] ?? ''))) {
                header('Content-Type: text/plain');
                echo preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($_GET['hub_challenge'] ?? ''));
                exit;
            }
            exit('{"ok":true}');
        }

        $n = WhatsAppService::ingestWebhook((int) $user['id'], Request::json());
        if ($n > 0) {
            Audit::log('whatsapp_webhook', ['messages' => $n], (int) $user['id']);
        }
        echo json_encode(['ok' => true, 'stored' => $n]);
        exit;
    }
}
