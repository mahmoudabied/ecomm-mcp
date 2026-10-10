<?php

namespace Webkul\MarketingAutomation\Engine\Steps;

use Webkul\MarketingAutomation\Engine\StepContext;
use Webkul\MarketingAutomation\Engine\StepOutcome;
use Webkul\MarketingAutomation\Models\Enrolment;

interface StepHandler
{
    /**
     * Execute one step of a flow version.
     *
     * @param  array<string, mixed>  $step
     */
    public function handle(Enrolment $enrolment, array $step, StepContext $context): StepOutcome;
}
