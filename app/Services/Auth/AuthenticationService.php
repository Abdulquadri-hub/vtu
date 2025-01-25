<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponseHandler;
use App\Services\ValidationService;
use App\Contracts\Auth\HashServiceInterface;
use App\Contracts\Auth\TokenServiceInterface;
use App\Contracts\Auth\UserRepositoryInterface;
use App\Contracts\Auth\NotificationServiceInterface;
use App\Contracts\Auth\AuthenticationServiceInterface;

class AuthenticationService implements AuthenticationServiceInterface {

    use ApiResponseHandler;

    private $userRepository;
    private $tokenService;
    private $hashService;
    private $authenticationService;
    private  $notificationService;

    public function __construct(UserRepositoryInterface $userRepository,
        TokenServiceInterface $tokenService, HashServiceInterface $hashService, AuthenticationServiceInterface $authenticationService, NotificationServiceInterface $notificationService) {
        $this->userRepository = $userRepository;
        $this->tokenService = $tokenService;
        $this->hashService = $hashService;
        $this->authenticationService = $authenticationService;
        $this->notificationService = $notificationService;

    }

    public function register(array $userData){

        try {

            ValidationService::validateRegistrationData($userData);

            if($this->userRepository->findByEmail($userData['email']) || $this->userRepository->findByPhone($userData['phone']) ){
                return ApiResponseHandler::errorResponse("User email or phone already registered");
            }

            $userData['pin'] = $this->hashService->make($userData['pin']);
            $userData['password'] = $this->hashService->make($userData['password']);

            $user = $this->userRepository->create($userData);

            $token =  $this->tokenService->generateEmailVerificationToken($user);

            $this->notificationService->sendEmailVerification($user, $token);

            return ApiResponseHandler::successResponse($user, "User registration was successful.", 201);

        } catch (\Throwable $th) {
            return ApiResponseHandler::errorResponse($th->getMessage(),500);
        }
    }

    public function login(array $credentials)
    {
        ValidationService::validateLoginCredentials($credentials);

        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user || !$this->hashService->verify($credentials['password'], $user->password)) {
            return ApiResponseHandler::errorResponse("Invalid credentials");
        }

        if (!$user->is_verified) {
            return ApiResponseHandler::errorResponse("Kindly verify your email first");
        }

        $token = $this->tokenService->generateAuthToken($user);

        return ApiResponseHandler::successResponse([
            'user' => $user,
            'token' => $token,
        ], "User login was successful.", 200);

    }

    public function logout(Request $request)
    {
        $this->tokenService->revokeCurrentToken($request);
    }

    public function forgotPassword(string $email)
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return ApiResponseHandler::errorResponse("User do not exists");
        }

        $token = $this->tokenService->generatePasswordResetToken($user);

        $this->notificationService->sendPasswordReset($user, $token);
    }

    public function resetPassword(array $data): bool
    {
        ValidationService::validateResetPasswordData($data);

        $user = $this->tokenService->verifyPasswordResetToken($data['token']);

        if (!$user) {
            return ApiResponseHandler::errorResponse("Invalid or expired token");
        }

        $hashedPassword = $this->hashService->make($data['password']);
        $this->userRepository->updatePassword($user, $hashedPassword);

        $this->tokenService->revokeAllTokens($user);

        $this->notificationService->sendPasswordChangeNotification($user);

        return true;
    }

    public function verifyEmail(string $token): bool
    {
        $user = $this->tokenService->verifyEmailToken($token);

        if (!$user) {
            return ApiResponseHandler::errorResponse("Invalid or expired token");
        }

        $this->userRepository->update($user, ['is_verified' => true]);

        return true;
    }

    public function verifyUserPin($request) : bool{

        $user = $request->user();

        if (!$user || !$this->hashService->verify($request['pin'], $user->pin)) {
            return false;
        }

        return true;
    }

    public function userVerification(array $verificationData){
        return "";
    }

}
