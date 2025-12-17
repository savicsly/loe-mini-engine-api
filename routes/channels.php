<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-user.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Also add without 'private-' prefix for Echo client compatibility
Broadcast::channel('user.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});
