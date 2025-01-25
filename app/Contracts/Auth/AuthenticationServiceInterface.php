<?php

namespace App\Contracts\Auth;

use App\Models\User;
use Illuminate\Http\Request;

interface AuthenticationServiceInterface {

    public function register(array $userData);

    public function login(array $credentials);

    public function logout(Request $request);

    public function forgotPassword(string $email);

    public function resetPassword(array $data): bool;

    public function verifyEmail(string $token): bool;

    public function userVerification(array $verificationData);

    public function verifyUserPin($request): bool;
}
