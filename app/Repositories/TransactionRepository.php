<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Contracts\Transaction\TransactionRepositoryInterface;

class TransactionRepository
{
    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {

            $data['reference'] = $data['reference'] ?? $this->generateReference();

            $transaction = Transaction::create($data);

            if (in_array($data['type'], ['wallet_funding', 'transfer', 'bill_payment'])) {
                $this->recordWalletTransaction($transaction);
            }

            // Log transaction creation

            return $transaction;
        });
    }

    public function find(string $reference): ?Transaction
    {
        return Transaction::where('reference', $reference)
            ->with(['user', 'profitRecord'])
            ->first();
    }

    public function update(Transaction $transaction, array $data): bool
    {
        return $transaction->update($data);
    }

    public function getUserTransactions(int $userId, array $filters = [])
    {
        $query = Transaction::where('user_id', $userId);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->latest()->get();
    }

    public function getPendingTransactions()
    {
        return Transaction::where('status', 'pending')
            ->with(['user'])
            ->latest()
            ->get();
    }

    public function getFailedTransactions()
    {
        return Transaction::where('status', 'failed')
            ->with(['user'])
            ->latest()
            ->get();
    }

    public function getTransactionsByType(string $type)
    {
        return Transaction::where('type', $type)
            ->with(['user'])
            ->latest()
            ->get();
    }

    public function getTransactionsByDateRange(string $startDate, string $endDate)
    {
        return Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->with(['user'])
            ->latest()
            ->get();
    }

    public function updateStatus(Transaction $transaction, string $status, ?string $message = null): bool
    {
        return DB::transaction(function () use ($transaction, $status, $message) {
            $updated = $transaction->update([
                'status' => $status,
                'meta_data' => array_merge($transaction->meta_data ?? [], [
                    'status_message' => $message,
                    'status_updated_at' => now()->toDateTimeString()
                ])
            ]);

            // if ($updated) {
            //     match($status) {
            //         'completed' => event(new TransactionCompleted($transaction)),
            //         'failed' => event(new TransactionFailed($transaction)),
            //         default => null
            //     };
            // }

            return $updated;
        });
    }

    public function recordProfit(Transaction $transaction, float $amount): bool
    {
        return DB::transaction(function () use ($transaction, $amount) {
            // Record profit
            $profitRecord = $transaction->profitRecord()->create([
                'amount' => $amount,
                'type' => 'transaction_profit'
            ]);

            // Update transaction profit amount
            $transaction->update(['profit' => $amount]);

            // event(new ProfitRecorded($transaction, $amount));

            return true;
        });
    }

    public function getProviderTransactions(int $providerId)
    {
        return Transaction::whereJsonContains('meta_data->provider_id', $providerId)
            ->with(['user'])
            ->latest()
            ->get();
    }

    public function getDailyTransactionTotal(): float
    {
        return Transaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getMonthlyTransactionTotal(): float
    {
        return Transaction::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('status', 'completed')
            ->sum('amount');
    }

    private function generateReference(): string
    {
        do {
            $reference = 'TXN' . strtoupper(Str::random(8));
        } while (Transaction::where('reference', $reference)->exists());

        return $reference;
    }

    private function recordWalletTransaction(Transaction $transaction): void
    {
        $wallet = $transaction->user->wallet;

        $wallet->transactions()->create([
            'type' => $this->getWalletTransactionType($transaction->type),
            'amount' => $transaction->amount,
            'previous_balance' => $wallet->balance,
            'current_balance' => $this->calculateNewBalance($wallet->balance, $transaction),
            'description' => $this->generateTransactionDescription($transaction),
            'transaction_id' => $transaction->id
        ]);
    }

    private function getWalletTransactionType(string $transactionType): string
    {
        return match($transactionType) {
            'wallet_funding' => 'credit',
            'transfer', 'bill_payment' => 'debit',
            default => 'unknown'
        };
    }

    private function calculateNewBalance(float $currentBalance, Transaction $transaction): float
    {
        return match($transaction->type) {
            'wallet_funding' => $currentBalance + $transaction->amount,
            'transfer', 'bill_payment' => $currentBalance - $transaction->amount,
            default => $currentBalance
        };
    }

    private function generateTransactionDescription(Transaction $transaction): string
    {
        return match($transaction->type) {
            'wallet_funding' => 'Wallet funding via ' . ($transaction->meta_data['payment_method'] ?? 'unknown'),
            'transfer' => 'Transfer to ' . ($transaction->meta_data['recipient'] ?? 'unknown'),
            'bill_payment' => 'Payment for ' . ($transaction->meta_data['bill_type'] ?? 'unknown'),
            default => 'Transaction: ' . $transaction->reference
        };
    }
}
