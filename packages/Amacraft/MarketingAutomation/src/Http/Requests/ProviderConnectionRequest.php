<?php

namespace Amacraft\MarketingAutomation\Http\Requests;

use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProviderConnectionRequest extends FormRequest
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
            'name'            => ['required', 'string', 'max:100'],
            'message_channel' => ['required', Rule::in([MessageChannel::Sms->value, MessageChannel::Whatsapp->value])],
            'driver'          => ['required', 'string', Rule::in(array_keys(config('automation-providers.drivers', [])))],
            'sender'          => ['required', 'string', 'max:50'],
            'credentials'     => ['nullable', 'array'],
        ];
    }

    /**
     * Validate the driver's channel match, its credential fields and update immutability.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $driverKey = $this->input('driver');
            $driver = config("automation-providers.drivers.{$driverKey}");

            if ($driver === null) {
                return;
            }

            if ($driver['channel'] !== $this->input('message_channel')) {
                $validator->errors()->add(
                    'driver',
                    trans('marketing_automation::app.providers.driver-channel-mismatch'),
                );

                return;
            }

            $this->validateImmutableDriver($validator);

            $this->validateCredentials($validator, $driverKey);
        });
    }

    /**
     * Keep only the credential fields the driver declares.
     *
     * @return array<string, string>
     */
    public function validatedCredentials(): array
    {
        $class = config("automation-providers.drivers.{$this->input('driver')}.class");

        if (! class_exists($class)) {
            return [];
        }

        $fields = Arr::pluck($class::fields(), 'name');

        return Arr::only((array) $this->input('credentials', []), $fields);
    }

    /**
     * A connection's driver and channel cannot change after creation.
     */
    protected function validateImmutableDriver($validator): void
    {
        if (! $this->isMethod('PUT') && ! $this->isMethod('PATCH')) {
            return;
        }

        $connection = $this->route('provider');

        if (! $connection instanceof ProviderConnection) {
            return;
        }

        if ($connection->driver !== $this->input('driver')
            || $connection->message_channel->value !== $this->input('message_channel')
        ) {
            $validator->errors()->add(
                'driver',
                trans('marketing_automation::app.providers.driver-immutable'),
            );
        }
    }

    /**
     * Required non-secret fields are always required; secret fields only on create.
     */
    protected function validateCredentials($validator, string $driverKey): void
    {
        $class = config("automation-providers.drivers.{$driverKey}.class");

        if (! class_exists($class)) {
            return;
        }

        $credentials = $this->input('credentials', []) ?? [];
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        foreach ($class::fields() as $field) {
            if (! in_array($field['name'], array_keys((array) $credentials), true) && ! $field['required']) {
                continue;
            }

            $value = $credentials[$field['name']] ?? null;

            if (isset($value) && ! is_string($value)) {
                $validator->errors()->add(
                    "credentials.{$field['name']}",
                    trans('validation.string', ['attribute' => $field['label']]),
                );

                continue;
            }

            $missing = in_array($value, [null, ''], true);

            if ($missing && $field['required'] && (! $field['secret'] || ! $isUpdate)) {
                $validator->errors()->add(
                    "credentials.{$field['name']}",
                    trans('validation.required', ['attribute' => $field['label']]),
                );
            }

            if (
                filled($value)
                && ! empty($field['numeric'])
                && ! preg_match('/^\d+$/', (string) $value)
            ) {
                $validator->errors()->add(
                    "credentials.{$field['name']}",
                    trans('marketing_automation::app.providers.must-be-digits', ['attribute' => $field['label']]),
                );
            }
        }
    }
}
