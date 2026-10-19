<?php

namespace Amacraft\MarketingAutomation\Engine\Steps;

use Amacraft\MarketingAutomation\Engine\StepContext;
use Amacraft\MarketingAutomation\Engine\StepOutcome;
use Amacraft\MarketingAutomation\Models\Enrolment;

class ExitStep implements StepHandler
{
    /**
     * End the journey.
     */
    public function handle(Enrolment $enrolment, array $step, StepContext $context): StepOutcome
    {
        return StepOutcome::exit();
    }
}
