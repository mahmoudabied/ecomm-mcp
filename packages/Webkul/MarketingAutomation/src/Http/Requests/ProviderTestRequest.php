<?php

namespace Webkul\MarketingAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderTestRequest extends FormRequest
{
    /**
     * Ensure the admin may manage messaging providers.
     */
    public function authorize(): bool
    {
        if (! bouncer()->hasPermission('marketing.automation.settings')) {
            abort(401, 'This action is unauthorized.');
        }

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
            'to' => ['required', 'string', 'max:30'],
        ];
    }
}
