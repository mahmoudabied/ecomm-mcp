<?php

namespace Webkul\MarketingAutomation\Engine\Steps;

use Webkul\MarketingAutomation\Engine\StepContext;
use Webkul\MarketingAutomation\Engine\StepOutcome;
use Webkul\MarketingAutomation\Models\Enrolment;

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
