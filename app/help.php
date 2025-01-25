<?php

// app/Services/Auth/AuthenticationService.php
class AuthenticationService implements AuthenticationServiceInterface
{
    private UserRepositoryInterface $userRepository;
    private HashServiceInterface $hashService;
    private TokenServiceInterface $tokenService;
    private NotificationServiceInterface $notificationService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        HashServiceInterface $hashService,
        TokenServiceInterface $tokenService,
        NotificationServiceInterface $notificationService
    ) {
        $this->userRepository = $userRepository;
        $this->hashService = $hashService;
        $this->tokenService = $tokenService;
        $this->notificationService = $notificationService;
    }

    public function register(array $userData): User
    {
        $this->validateRegistrationData($userData);

        // Check for existing user
        if ($this->userRepository->findByEmail($userData['email'])) {
            throw new UserAlreadyExistsException('Email already registered');
        }

        if ($this->userRepository->findByPhone($userData['phone'])) {
            throw new UserAlreadyExistsException('Phone number already registered');
        }

        // Hash password
        $userData['password'] = $this->hashService->make($userData['password']);

        // Create user
        $user = $this->userRepository->create($userData);

        // Generate verification token
        $token = $this->tokenService->generateEmailVerificationToken($user);

        // Send verification email
        $this->notificationService->sendEmailVerification($user, $token);

        // Create wallet for user
        event(new UserRegistered($user));

        return $user;
    }

    public function login(array $credentials): array
    {
        $this->validateLoginCredentials($credentials);

        // Find user
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user || !$this->hashService->verify($credentials['password'], $user->password)) {
            throw new InvalidCredentialsException('Invalid credentials');
        }

        if (!$user->is_verified) {
            throw new UnverifiedUserException('Please verify your email first');
        }

        // Generate tokens
        $token = $this->tokenService->generateAuthToken($user);

        // Log login
        event(new UserLoggedIn($user));

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(): void
    {
        $user = auth()->user();

        // Revoke token
        $this->tokenService->revokeCurrentToken();

        event(new UserLoggedOut($user));
    }

    public function forgotPassword(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return; // Don't reveal user existence
        }

        // Generate reset token
        $token = $this->tokenService->generatePasswordResetToken($user);

        // Send reset email
        $this->notificationService->sendPasswordReset($user, $token);
    }

    public function resetPassword(array $data): bool
    {
        $this->validateResetPasswordData($data);

        // Verify token
        $user = $this->tokenService->verifyPasswordResetToken($data['token']);

        if (!$user) {
            throw new InvalidTokenException('Invalid or expired token');
        }

        // Update password
        $hashedPassword = $this->hashService->make($data['password']);
        $this->userRepository->updatePassword($user, $hashedPassword);

        // Revoke all tokens
        $this->tokenService->revokeAllTokens($user);

        // Notify user
        $this->notificationService->sendPasswordChangeNotification($user);

        return true;
    }

    public function verifyEmail(string $token): bool
    {
        // Verify token
        $user = $this->tokenService->verifyEmailToken($token);

        if (!$user) {
            throw new InvalidTokenException('Invalid or expired token');
        }

        // Update user verification status
        $this->userRepository->update($user, ['is_verified' => true]);

        event(new UserVerified($user));

        return true;
    }

    private function validateRegistrationData(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function validateLoginCredentials(array $credentials): void
    {
        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}

// app/Http/Controllers/Auth/AuthController.php
class AuthController extends Controller
{
    private AuthenticationServiceInterface $authService;

    public function __construct(AuthenticationServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        try {
            $user = $this->authService->register($request->validated());

            return response()->json([
                'message' => 'Registration successful. Please check your email for verification.',
                'user' => new UserResource($user)
            ], 201);
        } catch (UserAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $result = $this->authService->login($request->validated());

            return response()->json([
                'message' => 'Login successful',
                'user' => new UserResource($result['user']),
                'token' => $result['token']
            ]);
        } catch (InvalidCredentialsException|UnverifiedUserException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }
}



// Service Provider Registration
// app/Providers/AuthServiceProvider.php
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(TokenServiceInterface::class, TokenService::class);
        $this->app->bind(TokenRepositoryInterface::class, TokenRepository::class);
        $this->app->bind(HashServiceInterface::class, HashService::class);
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
    }
}
