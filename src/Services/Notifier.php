<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;

/** Envía notificaciones (recordatorios, resumen diario) al mail del usuario usando el SMTP del sistema. */
final class Notifier
{
    public static function email(string $to, string $subject, string $text): void
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $host = (string) Config::get('smtp.host', '');
        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) Config::get('smtp.port', 465);
            $mail->SMTPAuth = true;
            $mail->Username = (string) Config::get('smtp.user');
            $mail->Password = (string) Config::get('smtp.password');
            $mail->SMTPSecure = Config::get('smtp.encryption') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->setFrom((string) Config::get('smtp.user'), (string) Config::get('smtp.from_name', 'Pipo'));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $text;
        $mail->send();
    }
}
