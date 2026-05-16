<?php

declare(strict_types=1);

namespace App\Auth\Port;

use App\Entity\User;

interface UserStore
{
    public function findByEmail(string $email): ?User;

    public function save(User $user): void;
}
