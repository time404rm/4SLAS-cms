<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body, $altBody = '') {
    $logDir = __DIR__ . '/../logs/mailer/';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . date('Y-m-d') . '.log';
    
    // Используем PHPMailer (а не mail())
    $mail = new PHPMailer(true);
    try {
        // Настройки SMTP (если включены)
        if ((int)getSetting('smtp_enabled') === 1) {
            $mail->isSMTP();
            $mail->Host       = getSetting('smtp_host');
            $mail->SMTPAuth   = true;
            $mail->Username   = getSetting('smtp_user');
            $mail->Password   = getSetting('smtp_pass');
            $mail->SMTPSecure = getSetting('smtp_encryption') ?: 'tls';
            $mail->Port       = (int)getSetting('smtp_port') ?: 587;
        } else {
            $mail->isMail();
        }
        
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom(getSetting('admin_email'), getSetting('site_name'));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        $mail->send();
        $result = true;
        $logEntry = date('Y-m-d H:i:s') . " | To: $to | Subject: $subject | Result: OK\n";
    } catch (Exception $e) {
        $result = false;
        $logEntry = date('Y-m-d H:i:s') . " | To: $to | Subject: $subject | Result: FAILED | Error: " . $mail->ErrorInfo . "\n";
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    return $result;
}