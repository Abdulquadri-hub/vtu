<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use App\Contracts\Auth\NotificationServiceInterface;

class NotificationService implements NotificationServiceInterface
{
    public function sendEmailVerification(User $user, string $token): void
    {
        $user->notify(new VerifyEmailNotification($token));
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        $user->notify(new ResetPasswordNotification($token));
    }

    public function sendPasswordChangeNotification(User $user): void
    {
        // $user->notify(new PasswordChangedNotification());
    }
}
