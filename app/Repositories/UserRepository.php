<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserRepository {

    public function create(array $data): User
    {
        return DB::transaction(function() use ($data) {
            $user = User::create($data);

            $user->wallet()->create([
                'balance' => 0,
                'currency' => 'NGN'
            ]);
            
            return $user;
        });
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return User::where('phone', $phone)->first();
    }

    public function findById(string $userId): ?User
    {
        return User::where('id', $userId)->first();
    }

    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function updatePassword(User $user, string $password): bool
    {
        return $user->update(['password' => $password]);
    }

    public function createProfile($data){

    }
}
