<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Escapa SIEMPRE cualquier dato antes de imprimirlo en HTML (anti XSS). */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layout'): void
    {
        $viewFile = APP_ROOT . '/views/' . $view . '.php';
        if (!preg_match('/^[a-z_\/]+$/', $view) || !is_file($viewFile)) {
            throw new \RuntimeException('Vista no encontrada: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();
        if ($layout === '') {
            echo $content;
            return;
        }
        require APP_ROOT . '/views/' . $layout . '.php';
    }

    public static function redirect(string $to): never
    {
        header('Location: ' . $to, true, 303);
        exit;
    }

    /** @param array<string,mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** Fecha UTC de la base -> texto en la zona horaria del usuario */
    public static function date(?string $utc, string $format = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        $tz = Auth::user()['timezone'] ?? Config::get('app.default_timezone');
        $d = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        return $d->setTimezone(new \DateTimeZone($tz))->format($format);
    }
}
