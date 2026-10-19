<?php

namespace Amacraft\MarketingAutomation\Engine\Steps;

use Amacraft\MarketingAutomation\Engine\StepContext;
use Amacraft\MarketingAutomation\Engine\StepOutcome;
use Amacraft\MarketingAutomation\Models\Enrolment;

interface StepHandler
{
    /**
     * Execute one step of a flow version.
     *
     * @param  array<string, mixed>  $step
     */
    public function handle(Enrolment $enrolment, array $step, StepContext $context): StepOutcome;
}
