<?php

namespace App\Contracts\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

interface WalletRepositoryInterface
{
    public function findByUser(User $user): Wallet;
    public function update(Wallet $wallet, array $data): bool;
    public function createTransaction(Wallet $wallet, array $data): WalletTransaction;
    public function getTransactions(User $user, array $filters = []): Collection;
    public function getBalance(User $user): float;
    public function getTransactionStats(User $user): array;
}

