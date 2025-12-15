<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class UserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}
