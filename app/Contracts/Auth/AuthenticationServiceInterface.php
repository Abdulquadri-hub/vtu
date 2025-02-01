<?php

namespace App\Contracts\Auth;

use App\Models\User;

interface AuthenticationServiceInterface {

    public function register(array $userData);

    public function login(array $credentials);

    public function logout($request);

    public function forgotPassword(string $email);

    public function resetPassword(array $data);

    public function verifyEmail(string $token);

    public function userVerification(array $verificationData);

    public function verifyUserPin($request);
}
