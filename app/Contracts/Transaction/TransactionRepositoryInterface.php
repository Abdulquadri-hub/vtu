<?php

namespace App\Contracts\Transaction;

use App\Models\Transaction;
use Illuminate\Support\Collection;


interface TransactionRepositoryInterface
{
    public function create(array $data): Transaction;
    public function find(string $reference): ?Transaction;
    public function update(Transaction $transaction, array $data): bool;
    public function getUserTransactions(int $userId, array $filters = []);
    public function getPendingTransactions();
    public function getFailedTransactions();
    public function getTransactionsByType(string $type);
    public function getTransactionsByDateRange(string $startDate, string $endDate);
    public function updateStatus(Transaction $transaction, string $status, ?string $message = null): bool;
    public function recordProfit(Transaction $transaction, float $amount): bool;
    public function getProviderTransactions(int $providerId);
    public function getDailyTransactionTotal(): float;
    public function getMonthlyTransactionTotal(): float;
}
