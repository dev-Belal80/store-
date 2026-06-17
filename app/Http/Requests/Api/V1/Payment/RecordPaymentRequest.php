<?php

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // invoice_id: null or 0 indicates a direct payment (not linked to an invoice)
            'invoice_id'     => ['nullable', 'integer', 'min:0'],
            // when invoice_id == 0, the request must include party_id (customer or supplier)
            'party_id'       => ['nullable', 'integer', 'min:1', 'required_if:invoice_id,0'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'notes'          => ['nullable', 'string'],
            'date'           => ['nullable', 'date'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
        ];
    }
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'يرجى تحديد رقم الفاتورة المرتبط بالدفع.',
            'amount.required'   => 'يرجى إدخال مبلغ الدفع.',
            'amount.min'        => 'يجب أن يكون مبلغ الدفع 0.01 أو أكثر.',
        
        ];
    }
}
