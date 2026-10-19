<?php

namespace Amacraft\MarketingAutomation\Tests;

use Amacraft\MarketingAutomation\Tests\Concerns\CreatesStorefrontProducts;
use Tests\TestCase;
use Webkul\Admin\Tests\Concerns\AdminTestBench;
use Webkul\Core\Tests\Concerns\CoreAssertions;

class MarketingAutomationTestCase extends TestCase
{
    use AdminTestBench, CoreAssertions, CreatesStorefrontProducts;
}
