<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\RecordPaymentRequest;
use App\Domain\Store\DTOs\RecordPaymentDTO;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domain\Store\Enums\PartyType;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function collectFromCustomer(RecordPaymentRequest $request): JsonResponse
    {
        $dto = RecordPaymentDTO::fromArray(
            data: $request->validated(),
            storeId: auth()->user()->getStoreId(),
            createdBy: auth()->id(),
        );

        $this->paymentService->collectFromCustomer($dto);

        return response()->json(['message' => 'تم تسجيل التحصيل بنجاح.']);
    }

    public function payToSupplier(RecordPaymentRequest $request): JsonResponse
    {
        $dto = RecordPaymentDTO::fromArray(
            data: $request->validated(),
            storeId: auth()->user()->getStoreId(),
            createdBy: auth()->id(),
        );

        $this->paymentService->payToSupplier($dto);

        return response()->json(['message' => 'تم تسجيل الدفعة بنجاح.']);
    }

    public function listCustomerPayments(Request $request, int $customerId): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listDirectPayments(
            auth()->user()->getStoreId(),
            PartyType::CUSTOMER,
            $customerId,
            $perPage
        );

        return response()->json($page);
    }

    public function listSupplierPayments(Request $request, int $supplierId): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listDirectPayments(
            auth()->user()->getStoreId(),
            PartyType::SUPPLIER,
            $supplierId,
            $perPage
        );

        return response()->json($page);
    }

    public function updatePayment(Request $request, int $paymentId): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'description' => 'nullable|string',
            'receipt_number' => 'nullable|string',
            'transaction_date' => 'nullable|date',
        ]);

        $this->paymentService->updateDirectPayment(auth()->user()->getStoreId(), $paymentId, $data);

        return response()->json(['message' => 'تم تحديث الفاتورة بنجاح.']);
    }

    public function deletePayment(int $paymentId): JsonResponse
    {
        $this->paymentService->deleteDirectPayment(auth()->user()->getStoreId(), $paymentId);

        return response()->json(['message' => 'تم حذف الفاتورة بنجاح.']);
    }
}
