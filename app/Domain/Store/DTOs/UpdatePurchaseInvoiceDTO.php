<?php

namespace App\Domain\Store\DTOs;

class UpdatePurchaseInvoiceDTO
{
    /** @param PurchaseItemDTO[] $items */
    public function __construct(
        public readonly int    $invoiceId,
        public readonly string $storeId,
        public readonly string $invoiceNumber,
        public readonly string $invoiceDate,
        public readonly string $supplierId,
        public readonly array  $items,
        public readonly float  $paidAmount,
        public readonly string $updatedBy,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data, int $invoiceId, string $storeId, string $updatedBy): self
    {
        $items = array_map(
            fn(array $i) => new PurchaseItemDTO(
                variantId: (int) $i['variant_id'],
                orderedQuantity: (float) ($i['ordered_quantity'] ?? $i['quantity'] ?? 0),
                receivedQuantity: (float) ($i['received_quantity'] ?? $i['quantity'] ?? 0),
                unitPrice: (float) $i['unit_price'],
            ),
            $data['items'] ?? []
        );

        return new self(
            invoiceId: $invoiceId,
            storeId: $storeId,
            invoiceNumber: $data['invoice_number'],
            invoiceDate: $data['invoice_date'] ?? date('Y-m-d'),
            supplierId: $data['supplier_id'],
            items: $items,
            paidAmount: (float) ($data['paid_amount'] ?? 0),
            updatedBy: $updatedBy,
            notes: $data['notes'] ?? null,
        );
    }
}
