<?php

namespace App\Services;

use App\Models\User;
use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Contracts\Auth\HashServiceInterface;

class HashService implements HashServiceInterface {

    public function make(string $value): string
    {
        return Hash::make($value);
    }

    public function verify(string $value, string $hashedValue): bool
    {
        return Hash::check($value, $hashedValue);
    }

}
