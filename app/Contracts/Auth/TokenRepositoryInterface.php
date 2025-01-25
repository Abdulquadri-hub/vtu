<?php

namespace App\Contracts\Auth;

use App\Models\User;

Interface TokenRepositoryInterface {
    
    public function createEmailVerificationToken(User $user, string $token): void;

    public function createPasswordResetToken(User $user, string $token): void;

    public function verifyEmailToken(string $token): ?User;

    public function verifyPasswordResetToken(string $token): ?User;

}
