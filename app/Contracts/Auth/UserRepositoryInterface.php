<?php

namespace App\Contracts\Auth;

use App\Models\User;

Interface UserRepositoryInterface {

    public function create(array $data): User;

    public function findByEmail(string $email): ?User;

    public function findByPhone(string $phone): ?User;

    public function update(User $user, array $data): bool;

    public function updatePassword(User $user, string $password): bool;

}
