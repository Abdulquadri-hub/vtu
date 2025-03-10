<?php

namespace App\Contracts\Wallet;

use App\Models\User;
use Illuminate\Support\Collection;

interface WalletServiceInterface
{
    public function initiateWalletFunding(User $user, float $amount): array;
    public function processWalletFunding(string $reference): bool;
    public function debitWallet(User $user, float $amount, string $description): bool;
    public function creditWallet(User $user, float $amount, string $description): bool;
    public function getBalance(User $user): float;
    public function getTransactions(User $user, array $filters = []);
}
