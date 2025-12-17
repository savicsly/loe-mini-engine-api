<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\UserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class RegisterUserAction
{
    public function execute(array $data): User|Model
    {
        $userData = new UserData(...$data);

        return User::query()
            ->create([
                'name' => $userData->name,
                'email' => $userData->email,
                'password' => bcrypt($userData->password),
            ]);
    }
}
