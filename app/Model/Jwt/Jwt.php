<?php

declare(strict_types=1);

namespace App\Model\Jwt;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use LogicException;
use Nette\Utils\DateTime;
use UnexpectedValueException;

class Jwt
{
    public const string ISSUER_ID = 'ikofein.cz';
    private const string ALGORITHM = 'HS256';
    private readonly string $key;

    public function __construct(string $base64Key)
    {
        $key = base64_decode($base64Key, true);
        if ($key === false) {
            throw new LogicException("Unable to decode invalid Base64 key for " . self::class);
        }

        $this->key = $key;
    }

    public function encode(string $subject, string $value, string $expire, ?string $audience = null): string
    {
        $exp = DateTime::from($expire)->getTimestamp();

        $payload = [
            'aud' => $audience,
            'iat' => time(),
            'iss' => self::ISSUER_ID,
            'exp' => $exp,
            'sub' => $subject,
            'val' => $value,
        ];

        return FirebaseJWT::encode($payload, $this->key, self::ALGORITHM);
    }

    public function decode(string $token, ?string $audience = null): array
    {
        try {
            $payload = FirebaseJWT::decode($token, new Key($this->key, self::ALGORITHM));
        } catch (UnexpectedValueException $e) {
            throw new JwtException('JWT Token invalid', 0, $e);
        }

        // Check mandatory claims presence
        if (isset(
                $payload->exp,
                $payload->iat,
                $payload->iss,
                $payload->sub,
                $payload->val
            ) === false) {
            throw new JwtException('JWT Token has no all required Claims');
        }

        // Check mandatory claims
        if (
            $payload->iss !== self::ISSUER_ID
            || ($payload->aud ?? null) !== $audience
        ) {
            throw new JwtException('JWT Token mandatory Claims doesn\'t match');
        }

        return [
            'subject' => $payload->sub,
            'value' => $payload->val,
        ];
    }

}
