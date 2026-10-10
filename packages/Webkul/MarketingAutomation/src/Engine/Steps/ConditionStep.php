<?php

namespace Webkul\MarketingAutomation\Engine\Steps;

use Webkul\MarketingAutomation\Engine\ConditionEvaluator;
use Webkul\MarketingAutomation\Engine\StepContext;
use Webkul\MarketingAutomation\Engine\StepOutcome;
use Webkul\MarketingAutomation\Models\Enrolment;

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
