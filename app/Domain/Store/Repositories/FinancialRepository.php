<?php

namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\IFinancialRepository;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\FinancialTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FinancialRepository implements IFinancialRepository
{
    public function getBalance(string $partyId, PartyType $partyType, string $storeId): float
    {
        $totals = FinancialTransaction::where('party_id', $partyId)
            ->where('party_type', $partyType)
            ->where('store_id', $storeId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as debit_total", [TransactionType::DEBIT->value])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as credit_total", [TransactionType::CREDIT->value])
            ->first();

        $debit = (float) ($totals?->debit_total ?? 0);
        $credit = (float) ($totals?->credit_total ?? 0);

        return $debit - $credit;
    }

    public function recordTransaction(
        string $storeId,
        TransactionType $transactionType,
        PartyType $partyType,
        string $partyId,
        float $amount,
        string $description = ''
    ): void {
        FinancialTransaction::create([
            'store_id' => $storeId,
            'type' => $transactionType,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'amount' => $amount,
            'description' => $description,
        ]);
    }

    public function addTransaction(
        string $storeId,
        PartyType $partyType,
        string $partyId,
        TransactionType $type,
        float $amount,
        string $referenceType,
        string $referenceId,
        string $description,
        string $createdBy
    ): void {
        FinancialTransaction::create([
            'store_id' => $storeId,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'type' => $type,
            'amount' => $amount,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => $createdBy,
        ]);
    }

    public function reverseTransactions(
        string $referenceType,
        string $referenceId,
        string $storeId,
        string $createdBy
    ): void {
        FinancialTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('store_id', $storeId)
            ->update(['reversed_by' => $createdBy, 'is_reversed' => true]);
    }

    public function getStatement(
        string    $partyId,
        PartyType $partyType,
        string    $storeId,
        ?string   $dateFrom = null,
        ?string   $dateTo   = null,
        int       $perPage  = 10,
    ): LengthAwarePaginator {
        // Build a query that prefers invoice/cash transaction dates when available,
        // falling back to the financial transaction created_at timestamp.
        $query = FinancialTransaction::query()
            ->where('financial_transactions.party_id', $partyId)
            ->where('financial_transactions.party_type', $partyType)
            ->where('financial_transactions.store_id', $storeId)
            ->leftJoin('sales_invoices', function ($join) {
                $join->on('financial_transactions.reference_id', '=', 'sales_invoices.id')
                     ->where('financial_transactions.reference_type', 'sales_invoice');
            })
            ->leftJoin('purchase_invoices', function ($join) {
                $join->on('financial_transactions.reference_id', '=', 'purchase_invoices.id')
                     ->where('financial_transactions.reference_type', 'purchase_invoice');
            })
            ->leftJoin('cash_transactions', function ($join) {
                $join->on('financial_transactions.reference_type', '=', 'cash_transactions.reference_type')
                     ->on('financial_transactions.reference_id', '=', 'cash_transactions.reference_id')
                     ->whereColumn('financial_transactions.amount', 'cash_transactions.amount');
            })

            ->select('financial_transactions.*')
            ->selectRaw("COALESCE(cash_transactions.transaction_date, sales_invoices.invoice_date, purchase_invoices.invoice_date, financial_transactions.created_at) as transaction_date");

        $dateExpr = "COALESCE(cash_transactions.transaction_date, sales_invoices.invoice_date, purchase_invoices.invoice_date, financial_transactions.created_at)";

        if ($dateFrom) {
            $query->whereRaw("{$dateExpr} >= ?", [$dateFrom . ' 00:00:00']);
        }
        if ($dateTo) {
            $query->whereRaw("{$dateExpr} <= ?", [$dateTo . ' 23:59:59']);
        }

        return $query
            ->orderByRaw("{$dateExpr} DESC")
            ->paginate($perPage)
            ->withQueryString();
    }
}
