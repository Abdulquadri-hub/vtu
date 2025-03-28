<?php

namespace App\Repositories;

use App\Models\ReservedAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReservedAccountRepository
{

    public function findByUser(User $user): ?ReservedAccount
    {
        return ReservedAccount::where('user_id', $user->id)->first();
    }

    /**
     * Find a reserved account by account number
     */
    public function findByAccountNumber(string $accountNumber): ?ReservedAccount
    {
        return ReservedAccount::where('account_number', $accountNumber)->first();
    }

    public function findByReference(string $reference): ?ReservedAccount
    {
        return ReservedAccount::where('account_reference', $reference)->first();
    }

    /**
     * Create a new reserved account
     */
    public function create(array $data): ReservedAccount
    {
        return ReservedAccount::create($data);
    }

    /**
     * Update a reserved account
     */
    public function update(ReservedAccount $reservedAccount, array $data): ReservedAccount
    {
        $reservedAccount->update($data);
        return $reservedAccount;
    }

    /**
     * Delete a reserved account
     */
    public function delete(ReservedAccount $reservedAccount): bool
    {
        return $reservedAccount->delete();
    }
}
