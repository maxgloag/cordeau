<?php

declare(strict_types=1);

namespace App\Auth\Port;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Exception\GoogleAuthenticationFailedException;

/**
 * Verifie un id_token Google (envoye par le client mobile via PKCE) et
 * renvoie les infos user normalisees.
 */
interface GoogleIdTokenVerifier
{
    /**
     * @throws GoogleAuthenticationFailedException si le token est invalide
     *         (signature, expiration, audience non whitelistee, etc.)
     */
    public function verify(string $idToken): GoogleUserInfo;
}
