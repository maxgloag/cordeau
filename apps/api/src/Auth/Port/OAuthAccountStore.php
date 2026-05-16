<?php

declare(strict_types=1);

namespace App\Auth\Port;

use App\Auth\Entity\OAuthAccount;

interface OAuthAccountStore
{
    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?OAuthAccount;

    public function save(OAuthAccount $account): void;
}
