<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserAuthService;

/**
 * LoraWAN Encryption/Decryption endpoints
 * Required by MeterApp when using LoraWAN radio communication
 */
final class EncryptController
{
    public function __construct(
        private UserAuthService $userAuthService
    ) {
    }

    /**
     * POST /api/encrypt
     * Body: token, deveui, port, plaintext (hex string)
     * Returns: ciphertext (hex string wrapped in quotes)
     */
    public function encrypt(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $deveui = (string) $request->input('deveui', '');
        $port = (string) $request->input('port', '');
        $plaintext = (string) $request->input('plaintext', '');

        if ($token === '' || $deveui === '' || $port === '' || $plaintext === '') {
            return Response::text('missing parameters', 400);
        }

        // Validate user token
        if ($this->userAuthService->validateToken($token) === null) {
            return Response::text('unauthorized', 401);
        }

        // Convert hex to binary
        $plainBinary = @hex2bin($plaintext);
        if ($plainBinary === false) {
            return Response::text('invalid plaintext hex', 400);
        }

        $portHex = @hex2bin($port);
        if ($portHex === false) {
            return Response::text('invalid port hex', 400);
        }

        // Generate deterministic encryption key based on deveui and port
        // Using AES-128-ECB (simple approach for meter compatibility)
        $key = $this->deriveKey($deveui, $portHex);

        // Pad to 16 bytes (AES block size)
        $padded = $this->pkcs7Pad($plainBinary, 16);

        // Encrypt using AES-128-ECB
        $cipherText = openssl_encrypt($padded, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        if ($cipherText === false) {
            return Response::text('encryption failed', 500);
        }

        // Return hex string wrapped in quotes (as app expects)
        return Response::text('"' . strtoupper(bin2hex($cipherText)) . '"', 200);
    }

    /**
     * POST /api/decrypt
     * Body: token, deveui, port, ciphered (hex string)
     * Returns: plaintext (hex string wrapped in quotes)
     */
    public function decrypt(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $deveui = (string) $request->input('deveui', '');
        $port = (string) $request->input('port', '');
        $ciphered = (string) $request->input('ciphered', '');

        if ($token === '' || $deveui === '' || $port === '' || $ciphered === '') {
            return Response::text('missing parameters', 400);
        }

        // Validate user token
        if ($this->userAuthService->validateToken($token) === null) {
            return Response::text('unauthorized', 401);
        }

        // Convert hex to binary
        $cipherBinary = @hex2bin($ciphered);
        if ($cipherBinary === false) {
            return Response::text('invalid ciphered hex', 400);
        }

        $portHex = @hex2bin($port);
        if ($portHex === false) {
            return Response::text('invalid port hex', 400);
        }

        // Derive key
        $key = $this->deriveKey($deveui, $portHex);

        // Decrypt using AES-128-ECB
        $plainPadded = openssl_decrypt($cipherBinary, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        if ($plainPadded === false) {
            return Response::text('decryption failed', 500);
        }

        // Remove PKCS7 padding
        $plainText = $this->pkcs7Unpad($plainPadded);
        if ($plainText === null) {
            return Response::text('invalid padding', 500);
        }

        // Return hex string wrapped in quotes (as app expects)
        return Response::text('"' . strtoupper(bin2hex($plainText)) . '"', 200);
    }

    /**
     * Derive encryption key from deveui and port
     */
    private function deriveKey(string $deveui, string $port): string
    {
        // Use first 16 bytes of SHA256 hash as AES-128 key
        $deveui = strtoupper(trim($deveui));
        return substr(hash('sha256', 'LORAWAN:' . $deveui . ':' . bin2hex($port), true), 0, 16);
    }

    /**
     * PKCS7 padding
     */
    private function pkcs7Pad(string $data, int $blockSize): string
    {
        $padLength = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($padLength), $padLength);
    }

    /**
     * PKCS7 unpadding
     */
    private function pkcs7Unpad(string $data): ?string
    {
        $length = strlen($data);
        if ($length === 0) {
            return null;
        }

        $padLength = ord($data[$length - 1]);
        if ($padLength < 1 || $padLength > 16) {
            return null;
        }

        // Verify padding
        for ($i = 0; $i < $padLength; $i++) {
            if (ord($data[$length - 1 - $i]) !== $padLength) {
                return null;
            }
        }

        return substr($data, 0, $length - $padLength);
    }
}
