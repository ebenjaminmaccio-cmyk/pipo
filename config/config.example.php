<?php
/**
 * Copiá este archivo como config/config.php y completá los valores.
 * config/config.php NUNCA se sube a git y debe quedar FUERA de public_html.
 */
declare(strict_types=1);

return [
    'app' => [
        'name'  => 'Pipo',
        // URL pública sin barra final, ej: https://asistente.tudominio.com
        'url'   => 'https://asistente.tudominio.com',
        // Clave de cifrado de 32 bytes en base64. Generala con:
        //   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
        // Si la perdés, no se pueden descifrar los tokens guardados.
        'key'   => 'CAMBIAR_POR_CLAVE_BASE64',
        'debug' => false,
        'default_timezone' => 'America/Argentina/Buenos_Aires',
        // Permite registrar nuevos usuarios desde /registro (false = solo vos)
        'allow_registration' => false,
    ],

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'u123456_pipo',
        'user'     => 'u123456_pipo',
        'password' => 'CAMBIAR',
    ],

    // API de Claude (https://console.anthropic.com -> API Keys)
    'anthropic' => [
        'api_key' => 'sk-ant-CAMBIAR',
        'model'   => 'claude-opus-5-5',
        'effort'  => 'medium',   // low | medium | high
    ],

    // Google Cloud Console -> APIs y servicios -> Credenciales -> ID de cliente OAuth (Aplicación web)
    // URI de redirección autorizada: {app.url}/google/callback
    // Habilitar: Gmail API y Google Calendar API
    'google' => [
        'client_id'     => '',
        'client_secret' => '',
    ],

    // SMTP para enviar notificaciones (recordatorios y resumen diario).
    // En Hostinger: smtp.hostinger.com, puerto 465, ssl, con una casilla creada en hPanel.
    'smtp' => [
        'host'       => 'smtp.hostinger.com',
        'port'       => 465,
        'encryption' => 'ssl',  // ssl | tls
        'user'       => 'asistente@tudominio.com',
        'password'   => 'CAMBIAR',
        'from_name'  => 'Pipo Asistente',
    ],

    // Token secreto para ejecutar el cron por URL si tu hosting no permite cron por CLI.
    // Generalo con: php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
    'cron_token' => 'CAMBIAR',
];
