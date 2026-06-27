<?php

namespace App\Domain\Store\DTOs;

final class UpdateSalesInvoiceDTO
{
    /** @param InvoiceItemDTO[] $items */
    public function __construct(
        public readonly int    $invoiceId,
        public readonly string $storeId,
        public readonly ?string $invoiceNumber,
        public readonly string $invoiceDate,
        public readonly string $customerId,
        public readonly array  $items,
        public readonly float  $discountAmount,
        public readonly float  $paidAmount,
        public readonly string $updatedBy,
        public readonly ?string $notes = null,
        public readonly ?string $salesRepName = null,
    ) {}

    public static function fromArray(array $data, int $invoiceId, string $storeId, string $updatedBy): self
    {
        $items = array_map(
            fn(array $i) => new InvoiceItemDTO(
                variantId: (int) $i['variant_id'],
                quantity: (float) $i['quantity'],
                unitPrice: (float) $i['unit_price'],
            ),
            $data['items'] ?? []
        );

        return new self(
            invoiceId: $invoiceId,
            storeId: $storeId,
            invoiceNumber: $data['invoice_number'] ?? null,
            invoiceDate: $data['invoice_date'] ?? date('Y-m-d'),
            customerId: $data['customer_id'],
            items: $items,
            discountAmount: (float) ($data['discount_amount'] ?? 0),
            paidAmount: (float) ($data['paid_amount'] ?? 0),
            updatedBy: $updatedBy,
            notes: $data['notes'] ?? null,
            salesRepName: $data['sales_rep_name'] ?? null,
        );
    }
}
