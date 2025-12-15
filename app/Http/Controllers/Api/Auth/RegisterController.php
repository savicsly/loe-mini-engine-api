<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Auth\Events\Registered;
use Throwable;

final class RegisterController extends Controller
{
    public function __construct(protected readonly RegisterUserAction $registerUserAction)
    {
    }

    public function __invoke(RegisterRequest $request)
    {
        $validated = $request->validated();
        try {
            $user = $this->registerUserAction->execute($validated);

            event(new Registered($user));

            return response()->json([
                'message' => 'Registration successful.',
                'user' => new UserResource($user),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Registration failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
