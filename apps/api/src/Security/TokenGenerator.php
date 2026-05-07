<?php

declare(strict_types=1);

namespace App\Security;

final class TokenGenerator
{
    private const SELECTOR_BYTES = 16;
    private const VERIFIER_BYTES = 32;
    private const ACCESS_TOKEN_TTL = 3600;      // 1 heure
    private const REFRESH_TOKEN_TTL = 2592000;  // 30 jours

    /**
     * @return array{token: string, selector: string, verifierHash: string, expiresAt: \DateTimeImmutable}
     */
    public function generateAccessToken(): array
    {
        $selector = bin2hex(random_bytes(self::SELECTOR_BYTES));
        $verifier = base64_encode(random_bytes(self::VERIFIER_BYTES));
        $verifierHash = password_hash($verifier, PASSWORD_BCRYPT);

        return [
            'token' => $selector . '.' . $verifier,
            'selector' => $selector,
            'verifierHash' => $verifierHash,
            'expiresAt' => new \DateTimeImmutable('+' . self::ACCESS_TOKEN_TTL . ' seconds'),
        ];
    }

    /**
     * SHA-256 pour le refresh token (aléatoire 32 bytes → lookup direct par hash possible,
     * sécurité suffisante — la résistance vient du caractère aléatoire, pas du coût de hash).
     *
     * @return array{refreshToken: string, refreshTokenHash: string, refreshExpiresAt: \DateTimeImmutable}
     */
    public function generateRefreshToken(): array
    {
        $raw = bin2hex(random_bytes(self::VERIFIER_BYTES));
        $hash = hash('sha256', $raw);

        return [
            'refreshToken' => $raw,
            'refreshTokenHash' => $hash,
            'refreshExpiresAt' => new \DateTimeImmutable('+' . self::REFRESH_TOKEN_TTL . ' seconds'),
        ];
    }
}
