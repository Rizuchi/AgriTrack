<?php

final class SmtpMailer
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/smtp_config.php';
    }

    public function send(string $recipient, string $subject, string $html): bool
    {
        $socket = @fsockopen($this->config['host'], (int) $this->config['port'], $errorNumber, $errorMessage, 15);
        if (!$socket) {
            return false;
        }

        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO localhost', 250);
            $this->command($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start SMTP TLS.');
            }
            $this->command($socket, 'EHLO localhost', 250);
            $this->command($socket, 'AUTH LOGIN', 334);
            $this->command($socket, base64_encode($this->config['username']), 334);
            $this->command($socket, base64_encode($this->config['password']), 235);
            $this->command($socket, 'MAIL FROM:<' . $this->config['from_email'] . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $recipient . '>', 250);
            $this->command($socket, 'DATA', 354);

            $message = 'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . ">\r\n"
                . 'To: <' . $recipient . ">\r\n"
                . 'Subject: ' . $subject . "\r\n"
                . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
                . $html;
            fwrite($socket, preg_replace('/^\./m', '..', $message) . "\r\n.\r\n");
            $this->expect($socket, 250);
            fwrite($socket, "QUIT\r\n");
            return true;
        } catch (Throwable $exception) {
            error_log('AgriTrack SMTP error: ' . $exception->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, int $expectedCode): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCode);
    }

    private function expect($socket, int $expectedCode): void
    {
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP connection closed.');
            }
        } while (isset($line[3]) && $line[3] === '-');

        $actualCode = (int) substr($line, 0, 3);
        if ($actualCode !== $expectedCode) {
            throw new RuntimeException('SMTP response ' . $actualCode . ': ' . trim($line));
        }
    }
}
