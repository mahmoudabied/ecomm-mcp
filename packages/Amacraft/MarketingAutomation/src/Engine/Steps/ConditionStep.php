<?php

namespace Amacraft\MarketingAutomation\Engine\Steps;

use Amacraft\MarketingAutomation\Engine\ConditionEvaluator;
use Amacraft\MarketingAutomation\Engine\StepContext;
use Amacraft\MarketingAutomation\Engine\StepOutcome;
use Amacraft\MarketingAutomation\Models\Enrolment;

class ConditionStep implements StepHandler
{
    /**
     * Create a new condition step instance.
     */
    public function __construct(
        protected ConditionEvaluator $evaluator,
    ) {}

    /**
     * Evaluate the rule against current data and take the branch.
     */
    public function handle(Enrolment $enrolment, array $step, StepContext $context): StepOutcome
    {
        return StepOutcome::branch(
            $this->evaluator->evaluate($step['rule'] ?? [], $enrolment),
        );
    }
}
