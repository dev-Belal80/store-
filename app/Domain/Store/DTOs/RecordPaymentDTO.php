<?php
namespace App\Domain\Store\DTOs;
final class RecordPaymentDTO
{
    public function __construct(
        public readonly string  $storeId,
        public readonly int     $invoiceId,
        public readonly ?int    $partyId,
        public readonly float   $amount,
        public readonly string  $createdBy,
        public readonly ?string $notes = null,
        public readonly ?string $date  = null, // null = today
        public readonly ?string $receiptNumber = null, // رقم فاتورة التحصيل
    ) {}

    public static function fromArray(array $data, string $storeId, string $createdBy): self
    {
        return new self(
            storeId:       $storeId,
            invoiceId:     isset($data['invoice_id']) ? (int) $data['invoice_id'] : 0,
            partyId:       isset($data['party_id']) ? (int) $data['party_id'] : null,
            amount:        (float) $data['amount'],
            createdBy:     $createdBy,
            notes:         $data['notes'] ?? null,
            date:          $data['date'] ?? null,
            receiptNumber: $data['receipt_number'] ?? null,
        );
    }
}