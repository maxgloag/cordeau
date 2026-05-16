<?php

declare(strict_types=1);

namespace App\Auth\Port;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Exception\GoogleAuthenticationFailedException;

/**
 * Port qui isole l'API du provider OAuth Google des details d'implementation
 * (KnpUOAuth2ClientBundle + league/oauth2-google). Permet de mocker en test.
 */
interface GoogleUserResolver
{
    /**
     * @throws GoogleAuthenticationFailedException
     */
    public function resolveFromCurrentRequest(): GoogleUserInfo;
}
