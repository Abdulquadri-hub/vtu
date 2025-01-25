<?php

namespace App\Contracts\Auth;

use App\Models\User;

interface NotificationServiceInterface {

    public function sendEmailVerification(User $user, string $token): void;

    public function sendPasswordReset(User $user, string $token): void;

    public function sendPasswordChangeNotification(User $user): void;


}
