<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthenticationService;

class AuthController extends Controller
{
    private $authService;

    public function __construct(AuthenticationService $authService){
        $this->authService = $authService;
    }

    public function register(Request $request){
        return $this->authService->register($request->all());
    }

    public function verifyEmail(Request $request){
        return $this->authService->verifyEmail($request->token);
    }

    public function login(Request $request){
        return $this->authService->login($request->all());
    }

    public function forgotPassword($email){
        return $this->authService->forgotPassword($email);
    }

    public function resetPassword(Request $request){
        return $this->authService->resetPassword($request->all());
    }

    public function verifyUserPin(Request $request){
        return $this->authService->verifyUserPin($request);
    }

    public function logout(Request $request){
        return $this->authService->logout($request);
    }


}
