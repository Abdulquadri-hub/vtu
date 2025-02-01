<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Auth\HashService;
use App\Services\Auth\TokenService;
use App\Traits\ApiResponseHandler;
use App\Services\Auth\ValidationService;
use App\Repositories\UserRepository;
use App\Services\Notifications\NotificationService;
use App\Contracts\Auth\AuthenticationServiceInterface;

class AuthenticationService implements AuthenticationServiceInterface {

    use ApiResponseHandler;

    private $userRepository;
    private $tokenService;
    private $hashService;
    private $authenticationService;
    private  $notificationService;
    private  $validateService;

    public function __construct(UserRepository $userRepository,
        TokenService $tokenService, HashService $hashService, NotificationService $notificationService,
        ValidationService $validateService) {
        $this->userRepository = $userRepository;
        $this->tokenService = $tokenService;
        $this->hashService = $hashService;
        $this->notificationService = $notificationService;
        $this->validateService = $validateService;

    }

    public function register(array $userData){

        try {

            $validation = $this->validateService->validateRegistrationData($userData);

            if ($validation !== true) {
                return $validation;
            }

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
        $validation = $this->validateService->validateLoginCredentials($credentials);

        if ($validation !== true) {
            return $validation;
        }

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

    public function logout($request)
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

        return ApiResponseHandler::successResponse([], "Password reset token sent successfully.");
    }

    public function resetPassword(array $data)
    {
        $validation = $this->validateService->validateResetPasswordData($data);

        if ($validation !== true) {
            return $validation;
        }

        $user = $this->tokenService->verifyPasswordResetToken($data['token']);

        if (!$user) {
            return ApiResponseHandler::errorResponse("Invalid or expired token");
        }

        $hashedPassword = $this->hashService->make($data['password']);
        $this->userRepository->updatePassword($user, $hashedPassword);

        $this->tokenService->revokeAllTokens($user);

        $this->notificationService->sendPasswordChangeNotification($user);

        return ApiResponseHandler::successResponse([], "Password was reset successfully.");
    }

    public function verifyEmail(string $token)
    {
        $user = $this->tokenService->verifyEmailToken($token);

        if (!$user) {
            return ApiResponseHandler::errorResponse("Invalid or expired token");
        }

        $this->userRepository->update($user, ['is_verified' => true, 'email_verified_at' => now()]);

        return $this->successResponse("Email verification was successfull");
    }

    public function verifyUserPin($request){

        $user = $request->user();

        if(!$request->has('pin')){
            return ApiResponseHandler::validationErrorResponse([], "User Pin is required");
        }

        if (!$this->hashService->verify($request['pin'], $user->pin)) {
            return ApiResponseHandler::errorResponse("invalid pin");
        }

        return ApiResponseHandler::successResponse($user, "User pin verification was successful");
    }

    public function userVerification(array $verificationData){
        return "";
    }

}
