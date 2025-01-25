<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use App\Contracts\Auth\TokenServiceInterface;
use App\Contracts\Auth\TokenRepositoryInterface;
use Illuminate\Http\Request;

class TokenService implements TokenServiceInterface {

    private TokenRepositoryInterface $tokenRepository;

    public function __construct(TokenRepositoryInterface $tokenRepository)
    {
        $this->tokenRepository = $tokenRepository;
    }

    public function generateAuthToken(User $user): string
    {
        return $user->createToken('auth-token')->plainTextToken;
    }

    public function generateEmailVerificationToken(User $user): string
    {
        $token = Str::random(64);

        $this->tokenRepository->createEmailVerificationToken($user, $token);

        return $token;
    }

    public function generatePasswordResetToken(User $user): string
    {
        $token = Str::random(64);

        $this->tokenRepository->createPasswordResetToken($user, $token);

        return $token;
    }

    public function verifyEmailToken(string $token): ?User
    {
        return $this->tokenRepository->verifyEmailToken($token);
    }

    public function verifyPasswordResetToken(string $token): ?User
    {
        return $this->tokenRepository->verifyPasswordResetToken($token);
    }

    public function revokeCurrentToken(Request $request): void
    {
        $request->user()->currentAccessToken()->delete();
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

}
