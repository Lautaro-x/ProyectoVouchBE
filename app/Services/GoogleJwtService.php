<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleJwtService
{
    private const JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';
    private const ISSUER   = 'https://accounts.google.com';

    public function verify(string $idToken): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) return null;

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header  = json_decode($this->b64Decode($encodedHeader),  true);
        $payload = json_decode($this->b64Decode($encodedPayload), true);

        if (!is_array($header) || !is_array($payload)) return null;

        if (($payload['iss'] ?? '')   !== self::ISSUER)                        return null;
        if (($payload['aud'] ?? '')   !== config('services.google.client_id')) return null;
        if (($payload['exp'] ?? 0)    <= time())                               return null;
        if (!($payload['email_verified'] ?? false))                            return null;

        $pem = $this->getPublicKeyPem($header['kid'] ?? '');
        if (!$pem) return null;

        $data      = "{$encodedHeader}.{$encodedPayload}";
        $signature = $this->b64Decode($encodedSignature);

        return openssl_verify($data, $signature, $pem, OPENSSL_ALGO_SHA256) === 1
            ? $payload
            : null;
    }

    private function getPublicKeyPem(string $kid): ?string
    {
        $keys = Cache::remember('google_jwks', 3600, fn () =>
            Http::timeout(5)->get(self::JWKS_URI)->json('keys', [])
        );

        foreach ($keys as $key) {
            if (($key['kid'] ?? '') === $kid && ($key['kty'] ?? '') === 'RSA') {
                return $this->jwkToPem($key);
            }
        }

        return null;
    }

    private function jwkToPem(array $jwk): string
    {
        $n = $this->b64Decode($jwk['n']);
        $e = $this->b64Decode($jwk['e']);

        if (ord($n[0]) & 0x80) $n = "\x00{$n}";
        if (ord($e[0]) & 0x80) $e = "\x00{$e}";

        $rsaSeq = $this->asn1Seq($this->asn1Int($n) . $this->asn1Int($e));
        $spki   = $this->asn1Seq(
            $this->asn1Seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00") .
            $this->asn1BitStr($rsaSeq)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function asn1Len(int $len): string
    {
        if ($len < 128) return chr($len);
        if ($len < 256) return "\x81" . chr($len);
        return "\x82" . chr($len >> 8) . chr($len & 0xff);
    }

    private function asn1Seq(string $content): string
    {
        return "\x30" . $this->asn1Len(strlen($content)) . $content;
    }

    private function asn1Int(string $bytes): string
    {
        return "\x02" . $this->asn1Len(strlen($bytes)) . $bytes;
    }

    private function asn1BitStr(string $content): string
    {
        $content = "\x00{$content}";
        return "\x03" . $this->asn1Len(strlen($content)) . $content;
    }

    private function b64Decode(string $input): string
    {
        $rem = strlen($input) % 4;
        if ($rem) $input .= str_repeat('=', 4 - $rem);
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
