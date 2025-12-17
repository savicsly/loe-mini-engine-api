<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $user->load('assets');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
