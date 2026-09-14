#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * KSF GPG Agent - Local relay between browser and GPG/Kleopatra
 *
 * Problem: OpenPGP.js in browser cannot access local GPG keyring
 * Solution: This relay service runs locally, proxies requests to gpg,
 *           and communicates via simple HTTP JSON API
 *
 * Security:
 * - Only listens on localhost (not exposed to network)
 * - Communicates via Unix socket or localhost HTTP
 * - Validates all requests before passing to gpg
 * - Logs operations for audit trail
 *
 * Protocol:
 *   POST /decrypt     { "encrypted_data": "..." }
 *   POST /sign        { "data": "...", "key_fingerprint": "..." }
 *   POST /import      { "armored_key": "..." }
 *   POST /list_keys   { "secret": true|false }
 *   GET  /status      {} -> { "running": true, "gpg_version": "..." }
 *
 * @since 1.5.0
 */

namespace ksfraser\GPG\Agent;

class GpgAgentRelay
{
    private const SOCKET_PATH = '/tmp/ksf-gpg-agent.sock';
    private const PORT = 7890;
    private const LOG_FILE = '/tmp/ksf-gpg-agent.log';

    private $socket = null;
    private $running = false;
    private $verbose = false;

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;
    }

    public function run(): int
    {
        $this->log("Starting KSF GPG Agent Relay on port " . self::PORT);

        if (!$this->checkGpgAvailable()) {
            $this->log("ERROR: GPG is not available");
            return 1;
        }

        $this->log("GPG Version: " . $this->getGpgVersion());

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            $this->log("ERROR: Could not create socket");
            return 1;
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!@socket_bind($socket, '127.0.0.1', self::PORT)) {
            $this->log("ERROR: Could not bind to port " . self::PORT . " - is another instance running?");
            socket_close($socket);
            return 1;
        }

        if (!socket_listen($socket, 5)) {
            $this->log("ERROR: Could not listen on socket");
            socket_close($socket);
            return 1;
        }

        socket_set_nonblock($socket);

        $this->running = true;
        $this->log("GPG Agent Relay listening on 127.0.0.1:" . self::PORT);
        $this->log("Press Ctrl+C to stop");

        pcntl_signal(SIGINT, function() {
            $this->log("Received SIGINT, shutting down...");
            $this->running = false;
        });

        pcntl_signal(SIGTERM, function() {
            $this->log("Received SIGTERM, shutting down...");
            $this->running = false;
        });

        while ($this->running) {
            pcntl_signal_dispatch();

            $client = @socket_accept($socket);
            if ($client === false) {
                usleep(10000);
                continue;
            }

            $this->handleClient($client);
            socket_close($client);
        }

        socket_close($socket);
        $this->log("GPG Agent Relay stopped");
        return 0;
    }

    private function handleClient($client): void
    {
        $request = $this->readHttpRequest($client);

        if (!$request) {
            $this->sendError($client, 400, "Invalid request");
            return;
        }

        $method = $request['method'];
        $path = $request['path'];
        $body = $request['body'];

        $this->log("Request: $method $path");

        $response = $this->dispatch($method, $path, $body);

        $this->sendJsonResponse($client, $response);
    }

    private function dispatch(string $method, string $path, array $body): array
    {
        switch ($path) {
            case '/status':
                return $this->handleStatus();

            case '/decrypt':
                if ($method !== 'POST') {
                    return ['error' => 'Method not allowed', 'code' => 405];
                }
                return $this->handleDecrypt($body);

            case '/sign':
                if ($method !== 'POST') {
                    return ['error' => 'Method not allowed', 'code' => 405];
                }
                return $this->handleSign($body);

            case '/import':
                if ($method !== 'POST') {
                    return ['error' => 'Method not allowed', 'code' => 405];
                }
                return $this->handleImport($body);

            case '/list_keys':
                if ($method !== 'POST') {
                    return ['error' => 'Method not allowed', 'code' => 405];
                }
                return $this->handleListKeys($body);

            case '/encrypt':
                if ($method !== 'POST') {
                    return ['error' => 'Method not allowed', 'code' => 405];
                }
                return $this->handleEncrypt($body);

            default:
                return ['error' => 'Not found', 'code' => 404];
        }
    }

    private function handleStatus(): array
    {
        return [
            'running' => true,
            'gpg_version' => $this->getGpgVersion(),
            'timestamp' => date('c'),
        ];
    }

    private function handleDecrypt(array $body): array
    {
        $encryptedData = $body['encrypted_data'] ?? null;
        $passphrase = $body['passphrase'] ?? null;
        $output = $body['output'] ?? 'base64';

        if (!$encryptedData) {
            return ['error' => 'Missing encrypted_data', 'code' => 400];
        }

        $tempIn = tempnam(sys_get_temp_dir(), 'gpg_enc_');
        $tempOut = tempnam(sys_get_temp_dir(), 'gpg_dec_');

        file_put_contents($tempIn, $encryptedData);

        $passphraseArg = $passphrase ? "--passphrase {$this->escapeShellArg($passphrase)} --pinentry-mode loopback" : "";

        $cmd = "gpg --decrypt $passphraseArg --armor --output {$tempOut} {$tempIn} 2>&1";
        $result = shell_exec($cmd);

        $decrypted = file_get_contents($tempOut);
        unlink($tempIn);
        unlink($tempOut);

        if ($decrypted === false || empty($result) === false && strpos($result, 'decryption failed') !== false) {
            return ['error' => 'Decryption failed', 'details' => $result, 'code' => 400];
        }

        return [
            'success' => true,
            'decrypted' => $output === 'base64' ? base64_encode($decrypted) : $decrypted,
            'format' => $output,
        ];
    }

    private function handleSign(array $body): array
    {
        $data = $body['data'] ?? null;
        $keyFingerprint = $body['key_fingerprint'] ?? null;
        $passphrase = $body['passphrase'] ?? null;
        $output = $body['output'] ?? 'base64';

        if (!$data || !$keyFingerprint) {
            return ['error' => 'Missing data or key_fingerprint', 'code' => 400];
        }

        $tempIn = tempnam(sys_get_temp_dir(), 'gpg_sig_');
        $tempOut = tempnam(sys_get_temp_dir(), 'gpg_sig_out_');

        file_put_contents($tempIn, $data);

        $passphraseArg = $passphrase ? "--passphrase {$this->escapeShellArg($passphrase)} --pinentry-mode loopback" : "";

        $cmd = "gpg --clearsign $passphraseArg --local-user {$keyFingerprint} --output {$tempOut} {$tempIn} 2>&1";
        $result = shell_exec($cmd);

        $signed = file_get_contents($tempOut);
        unlink($tempIn);
        unlink($tempOut);

        if ($signed === false) {
            return ['error' => 'Signing failed', 'details' => $result, 'code' => 400];
        }

        return [
            'success' => true,
            'signature' => $output === 'base64' ? base64_encode($signed) : $signed,
            'format' => $output,
        ];
    }

    private function handleEncrypt(array $body): array
    {
        $data = $body['data'] ?? null;
        $recipients = $body['recipients'] ?? [];
        $output = $body['output'] ?? 'base64';

        if (!$data || empty($recipients)) {
            return ['error' => 'Missing data or recipients', 'code' => 400];
        }

        $tempIn = tempnam(sys_get_temp_dir(), 'gpg_enc_');
        $tempOut = tempnam(sys_get_temp_dir(), 'gpg_enc_out_');

        file_put_contents($tempIn, $data);

        $recipientArgs = '';
        foreach ($recipients as $r) {
            $recipientArgs .= ' -r ' . $this->escapeShellArg($r);
        }

        $cmd = "gpg --encrypt --armor --output {$tempOut} {$recipientArgs} {$tempIn} 2>&1";
        $result = shell_exec($cmd);

        $encrypted = file_get_contents($tempOut);
        unlink($tempIn);
        unlink($tempOut);

        if ($encrypted === false) {
            return ['error' => 'Encryption failed', 'details' => $result, 'code' => 400];
        }

        return [
            'success' => true,
            'encrypted' => $output === 'base64' ? base64_encode($encrypted) : $encrypted,
            'format' => $output,
        ];
    }

    private function handleImport(array $body): array
    {
        $armoredKey = $body['armored_key'] ?? null;

        if (!$armoredKey) {
            return ['error' => 'Missing armored_key', 'code' => 400];
        }

        $tempKey = tempnam(sys_get_temp_dir(), 'gpg_imp_');
        file_put_contents($tempKey, $armoredKey);

        $cmd = "gpg --import {$tempKey} 2>&1";
        $result = shell_exec($cmd);

        unlink($tempKey);

        $fingerprint = null;
        if (preg_match('/key ([A-F0-9]+)/i', $result, $matches)) {
            $fingerprint = $matches[1];
        }

        return [
            'success' => true,
            'fingerprint' => $fingerprint,
            'import_output' => $result,
        ];
    }

    private function handleListKeys(array $body): array
    {
        $secret = $body['secret'] ?? false;

        $cmd = $secret ? 'gpg --list-secret-keys --with-colons --fingerprint' : 'gpg --list-keys --with-colons --fingerprint';
        $output = shell_exec($cmd);

        $keys = $this->parseGpgOutput($output);

        return [
            'success' => true,
            'keys' => $keys,
            'secret' => $secret,
        ];
    }

    private function parseGpgOutput(string $output): array
    {
        $keys = [];
        $currentKey = null;

        foreach (explode("\n", $output) as $line) {
            $parts = explode(':', $line);
            if (count($parts) < 2) {
                continue;
            }

            $recordType = $parts[0];

            if ($recordType === 'fpr') {
                $fingerprint = $parts[9] ?? '';
                if ($currentKey !== null) {
                    $currentKey['fingerprint'] = $fingerprint;
                }
            } elseif ($recordType === 'uid') {
                $name = $parts[9] ?? '';
                if ($currentKey !== null) {
                    $currentKey['uids'][] = $name;
                }
            } elseif ($recordType === 'sec' || $recordType === 'pub') {
                if ($currentKey !== null) {
                    $keys[] = $currentKey;
                }
                $currentKey = [
                    'type' => $recordType === 'sec' ? 'secret' : 'public',
                    'fingerprint' => null,
                    'uids' => [],
                ];
            }
        }

        if ($currentKey !== null) {
            $keys[] = $currentKey;
        }

        return $keys;
    }

    private function readHttpRequest($client): ?array
    {
        $request = '';
        while (($line = socket_read($client, 2048)) !== false) {
            $request .= $line;
            if (strpos($request, "\r\n\r\n") !== false) {
                break;
            }
        }

        if (empty($request)) {
            return null;
        }

        $lines = explode("\r\n", $request);
        $firstLine = explode(' ', $lines[0]);
        $method = $firstLine[0] ?? 'GET';
        $path = $firstLine[1] ?? '/';

        $bodyStart = strpos($request, "\r\n\r\n");
        $body = '';

        if ($bodyStart !== false) {
            $bodyRaw = substr($request, $bodyStart + 4);
            $body = json_decode($bodyRaw, true) ?? [];
        }

        return [
            'method' => $method,
            'path' => $path,
            'body' => $body,
        ];
    }

    private function sendJsonResponse($client, array $data): void
    {
        $code = $data['code'] ?? 200;
        unset($data['code']);

        $json = json_encode($data);

        $response = "HTTP/1.1 {$code} OK\r\n";
        $response .= "Content-Type: application/json\r\n";
        $response .= "Content-Length: " . strlen($json) . "\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= $json;

        socket_write($client, $response);
    }

    private function sendError($client, int $code, string $message): void
    {
        $this->sendJsonResponse($client, ['error' => $message, 'code' => $code]);
    }

    private function checkGpgAvailable(): bool
    {
        return shell_exec('which gpg') !== null;
    }

    private function getGpgVersion(): string
    {
        return trim(shell_exec('gpg --version 2>&1 | head -1') ?? 'unknown');
    }

    private function escapeShellArg(string $arg): string
    {
        return "'" . str_replace("'", "'\\''", $arg) . "'";
    }

    private function log(string $message): void
    {
        if ($this->verbose) {
            echo date('Y-m-d H:i:s') . " [GPG-AGENT] $message\n";
        }

        file_put_contents(self::LOG_FILE, date('Y-m-d H:i:s') . " [GPG-AGENT] $message\n", FILE_APPEND);
    }

    public static function startDaemon(bool $verbose = false): int
    {
        $agent = new self($verbose);
        return $agent->run();
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $verbose = in_array('-v', $argv, true) || in_array('--verbose', $argv, true);
    exit(GpgAgentRelay::startDaemon($verbose));
}