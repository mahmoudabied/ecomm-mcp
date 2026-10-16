<?php

namespace Webkul\MarketingAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutContactRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email'               => ['nullable', 'required_without:phone', 'email:filter', 'max:191'],
            'phone'               => ['nullable', 'required_without:email', 'string', 'max:30'],
            'consents'            => ['nullable', 'array'],
            'consents.email'      => ['boolean'],
            'consents.sms'        => ['boolean'],
            'consents.whatsapp'   => ['boolean'],
        ];
    }
}
