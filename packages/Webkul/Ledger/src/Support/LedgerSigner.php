<?php

namespace Webkul\Ledger\Support;

use OpenSSLAsymmetricKey;

class LedgerSigner
{
    private const P256_SPKI_HEADER = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

    public function __construct(
        private readonly ?string $signingSeed = null,
        private readonly ?string $fallbackKey = null,
    ) {}

    /**
     * Return the server's Ed25519 public key encoded as unpadded Base64URL (43 chars / 32 bytes).
     */
    public function publicKeyBase64Url(): string
    {
        $keypair = $this->resolveKeypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);

        return self::base64UrlEncode($publicKey);
    }

    /**
     * Sign a canonical ASCII message (such as a 64-character hex SHA-256 hash or epoch digest) with Ed25519.
     *
     * @return array{alg: string, pubkey: string, proof: string}
     */
    public function signHash(string $hexHash): array
    {
        $keypair = $this->resolveKeypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $signature = sodium_crypto_sign_detached($hexHash, $secretKey);

        return [
            'alg' => 'ed25519',
            'pubkey' => self::base64UrlEncode($publicKey),
            'proof' => self::base64UrlEncode($signature),
        ];
    }

    /**
     * Verify an Ed25519 or ES256 (ASN.1 DER or 64-byte IEEE P1363 WebAuthn r||s) detached signature.
     */
    public function verify(string $alg, string $pubkey, string $message, string $proofBase64Url): bool
    {
        $normalizedAlg = strtolower(trim($alg));

        if ($normalizedAlg === 'ed25519') {
            return $this->verifyEd25519($pubkey, $message, $proofBase64Url);
        }

        if ($normalizedAlg === 'es256') {
            return $this->verifyEs256($pubkey, $message, $proofBase64Url);
        }

        return false;
    }

    /**
     * Convert a 64-byte IEEE P1363 ECDSA (r || s) signature into ASN.1 DER format for OpenSSL.
     */
    public static function p1363ToDer(string $p1363Signature): string
    {
        $r = substr($p1363Signature, 0, 32);
        $s = substr($p1363Signature, 32, 32);

        $derR = self::encodeDerUnsignedInteger($r);
        $derS = self::encodeDerUnsignedInteger($s);
        $body = $derR.$derS;

        return "\x30".chr(strlen($body)).$body;
    }

    /**
     * Convert an ASN.1 DER ECDSA P-256 signature into a fixed 64-byte IEEE P1363 (r || s) buffer.
     */
    public static function derToP1363(string $derSignature): ?string
    {
        $totalLen = strlen($derSignature);

        if ($totalLen < 8 || $derSignature[0] !== "\x30") {
            return null;
        }

        $seqLen = ord($derSignature[1]);

        if ($seqLen + 2 !== $totalLen || $derSignature[2] !== "\x02") {
            return null;
        }

        $rLen = ord($derSignature[3]);
        $sTagOffset = 4 + $rLen;

        if ($rLen < 1 || $sTagOffset + 2 > $totalLen || $derSignature[$sTagOffset] !== "\x02") {
            return null;
        }

        $sLen = ord($derSignature[$sTagOffset + 1]);

        if ($sLen < 1 || $sTagOffset + 2 + $sLen !== $totalLen) {
            return null;
        }

        $rBytes = ltrim(substr($derSignature, 4, $rLen), "\x00");
        $sBytes = ltrim(substr($derSignature, $sTagOffset + 2, $sLen), "\x00");

        if (strlen($rBytes) > 32 || strlen($sBytes) > 32) {
            return null;
        }

        return str_pad($rBytes, 32, "\x00", STR_PAD_LEFT)
            .str_pad($sBytes, 32, "\x00", STR_PAD_LEFT);
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $encoded): ?string
    {
        $trimmed = trim($encoded);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9\-_=]+$/', $trimmed) !== 1) {
            return null;
        }

        $unpadded = rtrim($trimmed, '=');
        $remainder = strlen($unpadded) % 4;

        if ($remainder === 1) {
            return null;
        }

        $padded = $remainder > 0 ? $unpadded.str_repeat('=', 4 - $remainder) : $unpadded;
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function verifyEd25519(string $pubkey, string $message, string $proofBase64Url): bool
    {
        $pubBytes = self::base64UrlDecode($pubkey);
        $sigBytes = self::base64UrlDecode($proofBase64Url);

        if (
            $pubBytes === null
            || $sigBytes === null
            || strlen($pubBytes) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || strlen($sigBytes) !== SODIUM_CRYPTO_SIGN_BYTES
        ) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sigBytes, $message, $pubBytes);
    }

    private function verifyEs256(string $pubkey, string $message, string $proofBase64Url): bool
    {
        $pem = self::resolveEs256Pem($pubkey);
        $sigBytes = self::base64UrlDecode($proofBase64Url);

        if ($pem === null || $sigBytes === null || $sigBytes === '') {
            return false;
        }

        $candidates = [];

        if (self::derToP1363($sigBytes) !== null) {
            $candidates[] = $sigBytes;
        }

        if (strlen($sigBytes) === 64) {
            $candidates[] = self::p1363ToDer($sigBytes);
        }

        if ($candidates === []) {
            return false;
        }

        $publicKey = @openssl_pkey_get_public($pem);

        if (! $publicKey instanceof OpenSSLAsymmetricKey) {
            return false;
        }

        $details = openssl_pkey_get_details($publicKey);

        if (
            ! is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC
            || ($details['ec']['curve_name'] ?? '') !== 'prime256v1'
        ) {
            return false;
        }

        foreach ($candidates as $derSignature) {
            if (@openssl_verify($message, $derSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function resolveEs256Pem(string $pubkey): ?string
    {
        $trimmed = trim($pubkey);

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '-----BEGIN')) {
            return $trimmed;
        }

        $decoded = self::base64UrlDecode($trimmed);

        if ($decoded === null || $decoded === '') {
            return null;
        }

        if (str_contains($decoded, '-----BEGIN')) {
            return $decoded;
        }

        if (strlen($decoded) === 91 && $decoded[0] === "\x30") {
            return "-----BEGIN PUBLIC KEY-----\n"
                .chunk_split(base64_encode($decoded), 64, "\n")
                ."-----END PUBLIC KEY-----\n";
        }

        if (strlen($decoded) === 65 && $decoded[0] === "\x04") {
            $spki = self::P256_SPKI_HEADER.$decoded;

            return "-----BEGIN PUBLIC KEY-----\n"
                .chunk_split(base64_encode($spki), 64, "\n")
                ."-----END PUBLIC KEY-----\n";
        }

        return null;
    }

    private static function encodeDerUnsignedInteger(string $unsignedBytes): string
    {
        $trimmed = ltrim($unsignedBytes, "\x00");

        if ($trimmed === '') {
            $trimmed = "\x00";
        }

        if ((ord($trimmed[0]) & 0x80) !== 0) {
            $trimmed = "\x00".$trimmed;
        }

        return "\x02".chr(strlen($trimmed)).$trimmed;
    }

    /**
     * @return non-empty-string
     */
    private function resolveKeypair(): string
    {
        $seedHex = $this->signingSeed;

        if ($seedHex === null && function_exists('app') && app()->bound('config')) {
            $configured = config('ledger.signing_seed');
            $seedHex = is_string($configured) ? $configured : null;
        }

        if (is_string($seedHex) && strlen($seedHex) === 64 && ctype_xdigit($seedHex)) {
            /** @var non-empty-string $seed */
            $seed = (string) hex2bin($seedHex);
        } else {
            $appKey = $this->fallbackKey;

            if ($appKey === null && function_exists('app') && app()->bound('config')) {
                $configuredAppKey = config('app.key');
                $appKey = is_string($configuredAppKey) ? $configuredAppKey : null;
            }

            if (($appKey === null || $appKey === '') && function_exists('app') && app()->bound('env') && app()->isProduction()) {
                throw new \RuntimeException('LedgerSigner requires LUMINARA_LEDGER_SEED or APP_KEY in production.');
            }

            $ikm = ($appKey !== null && $appKey !== '') ? $appKey : 'luminara-dev-key';
            $seed = hash_hkdf('sha256', $ikm, SODIUM_CRYPTO_SIGN_SEEDBYTES, 'luminara-ledger-ed25519-v1');
        }

        /** @var non-empty-string $keypair */
        $keypair = sodium_crypto_sign_seed_keypair($seed);

        return $keypair;
    }
}
