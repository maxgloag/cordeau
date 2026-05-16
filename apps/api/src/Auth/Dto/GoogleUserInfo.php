<?php

declare(strict_types=1);

namespace App\Auth\Dto;

/**
 * Donnees normalisees recuperees aupres de Google apres echange du code OAuth
 * ou verification d'un id_token. Independant de la lib OAuth utilisee.
 */
final readonly class GoogleUserInfo
{
    public function __construct(
        public string $sub,
        public string $email,
        public bool $emailVerified,
        public ?string $name = null,
    ) {
    }
}
