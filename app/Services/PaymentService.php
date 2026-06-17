<?php

// ══════════════════════════════════════════════════════════════════
// PaymentService.php — تحصيل وسداد نقدي بدون فاتورة
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Domain\Store\DTOs\RecordPaymentDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function __construct(private CacheService $cacheService) {}

    /**
     * Apply a payment to a sales invoice (customer payment).
     */
    public function collectFromCustomer(RecordPaymentDTO $dto): void
    {
        DB::transaction(function () use ($dto) {

            $invoice = \App\Models\SalesInvoice::where('store_id', $dto->storeId)->find($dto->invoiceId);

            if ($dto->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
                ]);
            }

            // Determine customer id: if invoiceId > 0 use invoice, otherwise use provided partyId for direct payment
            if (($dto->invoiceId ?? 0) > 0) {
                $customerId = $invoice->customer_id;
            } else {
                if (!$dto->partyId) {
                    throw ValidationException::withMessages(['party_id' => 'يرجى تحديد العميل عند إنشاء قيد تحصيل مباشر.']);
                }
                $customerId = $dto->partyId;
            }

            // قيد credit للعميل (يُقلل دينه) مع إشارة إلى الفاتورة
            // reference_type/reference_id vary for direct vs invoice-linked payments
            if (($dto->invoiceId ?? 0) > 0) {
                $refType = 'sales_invoice';
                $refId = $invoice->id;
                $description = $dto->notes ?? "تحصيل من الفاتورة: {$invoice->invoice_number}";
            } else {
                $refType = 'direct_payment';
                $refId = $customerId; // for direct payments we store party id in reference_id
                $description = $dto->notes ?? "تحصيل نقدي مباشر من العميل: {$customerId}";
            }

            // Generate receipt number when not provided
            $receiptNumber = $dto->receiptNumber;
            if (empty($receiptNumber)) {
                $year = now()->year;
                $last = FinancialTransaction::withoutGlobalScopes()
                    ->where('store_id', $dto->storeId)
                    ->whereYear('created_at', $year)
                    ->whereNotNull('receipt_number')
                    ->count();
                $receiptNumber = sprintf('RC-%d-%04d', $year, $last + 1);
            }

            $ft = FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $customerId,
                'type'           => TransactionType::CREDIT,
                'amount'         => $dto->amount,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'receipt_number' => $receiptNumber,
                'created_by'     => $dto->createdBy,
            ]);

            // قيد نقدي وارد مرتبط بالفاتورة أو كقيد مباشر
            CashTransaction::create([
                'store_id'         => $dto->storeId,
                'type'             => CashTransactionType::IN,
                'amount'           => $dto->amount,
                'reference_type'   => $refType,
                'reference_id'     => $refId,
                'description'      => $description,
                'transaction_date' => $dto->date ?? today(),
                'created_by'       => $dto->createdBy,
            ]);

            // تحديث رصيد الفاتورة
            // Update invoice balances only when invoice-linked
            if (($dto->invoiceId ?? 0) > 0) {
                $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $dto->amount;
                $invoice->remaining_amount = max(0, ($invoice->remaining_amount ?? ($invoice->total_amount - ($invoice->paid_amount ?? 0))) - $dto->amount);
                $invoice->save();
            }

            $this->cacheService->invalidateCustomerBalance($customerId);
            $this->cacheService->invalidateCashBalance($dto->storeId);
        });
    }

    /**
     * List direct payments for a party (customer or supplier).
     * Returns FinancialTransaction records representing direct payments.
     */
    public function listDirectPayments(int $storeId, string $partyType, int $partyId, int $perPage = 50): LengthAwarePaginator
    {
        $query = FinancialTransaction::query()
            ->where('store_id', $storeId)
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->whereIn('reference_type', ['direct_payment', 'sales_invoice', 'purchase_invoice'])
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    /**
     * Update a direct payment (financial transaction) and try to update matching cash transaction.
     */
    public function updateDirectPayment(int $storeId, int $paymentId, array $data): void
    {
        DB::transaction(function () use ($storeId, $paymentId, $data) {
            $ft = FinancialTransaction::where('store_id', $storeId)
                ->where('id', $paymentId)
                ->whereIn('reference_type', ['direct_payment', 'sales_invoice', 'purchase_invoice'])
                ->firstOrFail();

            if (isset($data['amount']) && $data['amount'] <= 0) {
                throw ValidationException::withMessages(['amount' => 'المبلغ يجب أن يكون أكبر من صفر.']);
            }

            $oldAmount = $ft->amount;

            $ft->fill(array_filter([
                'amount' => $data['amount'] ?? null,
                'description' => $data['description'] ?? null,
                'receipt_number' => $data['receipt_number'] ?? null,
            ]));

            $ft->save();

            // try to find corresponding cash transaction
            $cash = CashTransaction::where('store_id', $storeId)
                ->whereIn('reference_type', ['direct_payment', 'sales_invoice', 'purchase_invoice'])
                ->where('reference_id', $ft->party_id)
                ->where('amount', $oldAmount)
                ->first();

            if ($cash) {
                $cash->amount = $data['amount'] ?? $cash->amount;
                if (isset($data['description'])) $cash->description = $data['description'];
                if (isset($data['transaction_date'])) $cash->transaction_date = $data['transaction_date'];
                $cash->save();
            }

            // invalidate caches
            if ($ft->party_type === PartyType::CUSTOMER) {
                $this->cacheService->invalidateCustomerBalance($ft->party_id);
            } else {
                $this->cacheService->invalidateSupplierBalance($ft->party_id);
            }
            $this->cacheService->invalidateCashBalance($storeId);
        });
    }

    /**
     * Delete a direct payment and its associated cash transaction if found.
     */
    public function deleteDirectPayment(int $storeId, int $paymentId): void
    {
        DB::transaction(function () use ($storeId, $paymentId) {
            $ft = FinancialTransaction::where('store_id', $storeId)
                ->where('id', $paymentId)
                ->where('reference_type', 'direct_payment')
                ->firstOrFail();

            // try to find corresponding cash transaction
            $cash = CashTransaction::where('store_id', $storeId)
                ->where('reference_type', 'direct_payment')
                ->where('reference_id', $ft->party_id)
                ->where('amount', $ft->amount)
                ->first();

            $partyId = $ft->party_id;
            $partyType = $ft->party_type;

            $ft->delete();

            if ($cash) {
                $cash->delete();
            }

            if ($partyType === PartyType::CUSTOMER) {
                $this->cacheService->invalidateCustomerBalance($partyId);
            } else {
                $this->cacheService->invalidateSupplierBalance($partyId);
            }
            $this->cacheService->invalidateCashBalance($storeId);
        });
    }

    /**
     * دفع نقدي لمورد (بدون فاتورة).
     */
    public function payToSupplier(RecordPaymentDTO $dto): void
    {
        DB::transaction(function () use ($dto) {

            $invoice = \App\Models\PurchaseInvoice::where('store_id', $dto->storeId)->find($dto->invoiceId);

            if ($dto->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
                ]);
            }

            if (($dto->invoiceId ?? 0) > 0) {
                $supplierId = $invoice->supplier_id;
            } else {
                if (!$dto->partyId) {
                    throw ValidationException::withMessages(['party_id' => 'يرجى تحديد المورد عند إنشاء قيد دفع مباشر.']);
                }
                $supplierId = $dto->partyId;
            }

            // قيد credit للمورد (يُقلل ما علينا له) مع إشارة إلى الفاتورة
            if (($dto->invoiceId ?? 0) > 0) {
                $refType = 'purchase_invoice';
                $refId = $invoice->id;
                $description = $dto->notes ?? "دفع للفاتورة: {$invoice->invoice_number}";
            } else {
                $refType = 'direct_payment';
                $refId = $supplierId; // store party id for direct payments
                $description = $dto->notes ?? "دفع نقدي مباشر للمورد: {$supplierId}";
            }

            // generate receipt number for supplier/direct payments if missing
            $receiptNumber = $dto->receiptNumber;
            if (empty($receiptNumber)) {
                $year = now()->year;
                $last = FinancialTransaction::withoutGlobalScopes()
                    ->where('store_id', $dto->storeId)
                    ->whereYear('created_at', $year)
                    ->whereNotNull('receipt_number')
                    ->count();
                $receiptNumber = sprintf('RC-%d-%04d', $year, $last + 1);
            }

            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::SUPPLIER,
                'party_id'       => $supplierId,
                'type'           => TransactionType::CREDIT,
                'amount'         => $dto->amount,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'receipt_number' => $receiptNumber,
                'created_by'     => $dto->createdBy,
            ]);

            // cash out
            CashTransaction::create([
                'store_id'         => $dto->storeId,
                'type'             => CashTransactionType::OUT,
                'amount'           => $dto->amount,
                'reference_type'   => $refType,
                'reference_id'     => $refId,
                'description'      => $description,
                'transaction_date' => $dto->date ?? today(),
                'created_by'       => $dto->createdBy,
            ]);

            if (($dto->invoiceId ?? 0) > 0) {
                // update invoice balance only for invoice-linked payments
                $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $dto->amount;
                $invoice->remaining_amount = max(0, ($invoice->remaining_amount ?? ($invoice->total_amount - ($invoice->paid_amount ?? 0))) - $dto->amount);
                $invoice->save();
            }

            $this->cacheService->invalidateSupplierBalance($supplierId);
            $this->cacheService->invalidateCashBalance($dto->storeId);
        });
    }
}
