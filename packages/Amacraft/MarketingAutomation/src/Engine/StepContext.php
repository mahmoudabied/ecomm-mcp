<?php

namespace Amacraft\MarketingAutomation\Engine;

use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\Engine\Steps\StepHandler;
use Amacraft\MarketingAutomation\Enums\StepType;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\FlowVersion;
use Amacraft\MarketingAutomation\Rendering\ContextBuilder;

class StepContext
{
    /**
     * Cache the render context after the first build.
     */
    protected ?array $renderContext = null;

    /**
     * The key of the step currently being executed.
     */
    public string $currentStepKey = '';

    /**
     * Create a new step context instance.
     */
    public function __construct(
        public readonly Flow $flow,
        public readonly FlowVersion $flowVersion,
        public readonly Enrolment $enrolment,
        public readonly Contact $contact,
        public readonly Channel $channel,
        public readonly string $timezone,
        public readonly ContextBuilder $contextBuilder,
    ) {}

    /**
     * The variable context used to render message content.
     */
    public function renderContext(): array
    {
        return $this->renderContext ??= $this->contextBuilder->forEnrolment($this->enrolment);
    }

    /**
     * Resolve the handler behind a step type.
     */
    public function handlerFor(array $step): StepHandler
    {
        $type = StepType::from($step['type']);

        $class = match ($type) {
            StepType::Wait        => Steps\WaitStep::class,
            StepType::Condition   => Steps\ConditionStep::class,
            StepType::SendEmail,
            StepType::SendSms,
            StepType::SendWhatsapp => Steps\SendStep::class,
            StepType::Exit         => Steps\ExitStep::class,
        };

        return app($class);
    }
}
