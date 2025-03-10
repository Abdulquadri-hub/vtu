<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;


class WalletRepository{

    public function findByUser(User $user): Wallet
    {
        return $user->wallet()->firstOrFail();
    }

    public function update(Wallet $wallet, array $data): bool
    {
        return $wallet->update($data);
    }

    public function createTransaction(Wallet $wallet, array $data): WalletTransaction
    {
        return $wallet->transactions()->create($data);
    }

    public function getTransactions(User $user, array $filters = []): Collection
    {
        $query = $user->wallet->transactions()->latest();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['minimum_amount'])) {
            $query->where('amount', '>=', $filters['minimum_amount']);
        }

        if (isset($filters['maximum_amount'])) {
            $query->where('amount', '<=', $filters['maximum_amount']);
        }

        return $query->get();
    }

    public function getBalance(User $user): float
    {
        return $user->wallet->balance;
    }

    public function getTransactionStats(User $user): array
    {
        $wallet = $user->wallet;

        return [
            'total_credit' => $wallet->transactions()
                ->where('type', 'credit')
                ->sum('amount'),
            'total_debit' => $wallet->transactions()
                ->where('type', 'debit')
                ->sum('amount'),
            'transaction_count' => $wallet->transactions()->count(),
            'last_transaction' => $wallet->transactions()
                ->latest()
                ->first()
        ];
    }
}
