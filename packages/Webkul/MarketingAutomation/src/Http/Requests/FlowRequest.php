<?php

namespace Webkul\MarketingAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Webkul\MarketingAutomation\Database\Seeders\FlowTemplates;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\TriggerType;

class FlowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! bouncer()->hasPermission('marketing.automation.flows.manage')) {
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
            'name'                 => ['required', 'string', 'max:150'],
            'template_key'         => ['nullable', 'string'],
            'trigger_type'         => ['required', new Enum(TriggerType::class)],
            'trigger_config'       => ['nullable', 'array'],
            'type'                 => ['required', new Enum(FlowType::class)],
            'channel_ids'          => ['required', 'array', 'min:1'],
            'channel_ids.*'        => ['integer', 'exists:channels,id'],
            'reentry_window_hours' => ['nullable', 'integer', 'between:0,8760'],
            'exit_goals'           => ['nullable', 'array'],
            'exit_goals.*.type'    => ['in:order_placed'],
            'draft_definition'     => ['nullable', 'array'],
        ];
    }

    /**
     * Validate the template key against the prebuilt templates.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $templateKey = $this->input('template_key');

            if ($templateKey !== null && FlowTemplates::find($templateKey) === null) {
                $validator->errors()->add('template_key', trans('validation.exists', ['attribute' => $templateKey]));
            }
        });
    }
}
