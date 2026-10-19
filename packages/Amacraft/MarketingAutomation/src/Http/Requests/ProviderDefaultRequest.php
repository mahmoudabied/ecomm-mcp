<?php

namespace Amacraft\MarketingAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\ProviderConnection;

class ProviderDefaultRequest extends FormRequest
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
            'channel_id'             => ['required', 'integer', 'exists:channels,id'],
            'message_channel'        => ['required', Rule::in([MessageChannel::Sms->value, MessageChannel::Whatsapp->value])],
            'provider_connection_id' => ['nullable', 'integer', 'exists:automation_provider_connections,id'],
        ];
    }

    /**
     * The chosen connection must serve the submitted message channel.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $connectionId = $this->input('provider_connection_id');

            if ($connectionId === null) {
                return;
            }

            $connection = ProviderConnection::query()->find($connectionId);

            if ($connection === null || $connection->message_channel->value !== $this->input('message_channel')) {
                $validator->errors()->add(
                    'provider_connection_id',
                    trans('marketing_automation::app.providers.defaults-channel-mismatch'),
                );
            }
        });
    }
}
