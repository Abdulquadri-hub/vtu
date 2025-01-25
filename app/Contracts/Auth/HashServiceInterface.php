<?php

namespace App\Contracts\Auth;

Interface HashServiceInterface {

    public function make(string $value): string;
    
    public function verify(string $value, string $hashedValue): bool;
}
