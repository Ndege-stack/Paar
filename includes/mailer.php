<?php
/**
 * =====================================================================
 * PAAR — includes/mailer.php
 * ---------------------------------------------------------------------
 * Single PHPMailer wrapper used by both `cron.php` (reminder engine) and
 * the password-reset flow. Discovers PHPMailer in either of the two
 * vendor layouts we support and exposes a tiny API:
 *
 *   if (paar_mail_available()) { paar_send_mail($to, $name, $subj, $html); }
 *
 * If PHPMailer is not installed, paar_mail_available() returns false and
 * paar_send_mail() short-circuits to a logged no-op so callers can fall
 * back to in-app notifications without crashing.
 * =====================================================================
 */

require_once __DIR__ . '/../database.php';   // pulls config.php

if (!function_exists('paar_mail_log')) {
    function paar_mail_log(string $msg): void {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        @file_put_contents(LOG_PATH . '/mailer.log', $line, FILE_APPEND);
    }
}

if (!function_exists('paar_mail_bootstrap')) {
    /**
     * Locate and load PHPMailer from one of the supported layouts.
     * Returns true if loaded, false otherwise. Memoised after first call.
     */
    function paar_mail_bootstrap(): bool {
        static $ready = null;
        if ($ready !== null) return $ready;

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $ready = true;
        }

        $candidates = [
            __DIR__ . '/../vendor/phpmailer/PHPMailer.php',         // flat
            __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php',     // upstream
        ];
        foreach ($candidates as $base) {
            if (is_file($base)) {
                $dir = dirname($base);
                require_once $dir . '/Exception.php';
                require_once $dir . '/PHPMailer.php';
                require_once $dir . '/SMTP.php';
                return $ready = true;
            }
        }
        return $ready = false;
    }
}

if (!function_exists('paar_mail_available')) {
    function paar_mail_available(): bool {
        return paar_mail_bootstrap();
    }
}

if (!function_exists('paar_send_mail')) {
    /**
     * Send a transactional HTML email. Returns true on success.
     * Quietly logs and returns false when PHPMailer or SMTP is unavailable.
     */
    function paar_send_mail(string $to, string $toName, string $subject, string $bodyHtml): bool {
        if (!paar_mail_bootstrap()) {
            paar_mail_log("SKIP (PHPMailer not installed) to {$to} — {$subject}");
            return false;
        }
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = trim(strip_tags(str_replace(
                ['<br>', '<br/>', '<br />'], "\n", $bodyHtml
            )));
            $mail->send();
            return true;
        } catch (Throwable $e) {
            paar_mail_log('ERROR to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }
}
