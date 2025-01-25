<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\Validator;

class ValidationService {

    use ApiResponseHandler;

    public static function validateRegistrationData(array $data)
    {
        $validator = Validator::make($data, [
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'pin' => 'required|string|max:4',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return self::validationErrorResponse($errors);
        }
    }

    public static function validateLoginCredentials(array $credentials)
    {
        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return self::validationErrorResponse($errors);
        }
    }

    public static function validateResetPasswordData(array $credentials)
    {
        $validator = Validator::make($credentials, [
            'token' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return self::validationErrorResponse($errors);
        }
    }

}
