<?php

namespace Webkul\MarketingAutomation\Engine;

use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Engine\Steps\StepHandler;
use Webkul\MarketingAutomation\Enums\StepType;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\FlowVersion;
use Webkul\MarketingAutomation\Rendering\ContextBuilder;

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
