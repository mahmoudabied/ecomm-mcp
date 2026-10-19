<?php

namespace Amacraft\Klaviyo\Tests;

use Amacraft\MarketingAutomation\Tests\Concerns\CreatesStorefrontProducts;
use Tests\TestCase;
use Webkul\Admin\Tests\Concerns\AdminTestBench;
use Webkul\Core\Tests\Concerns\CoreAssertions;

class KlaviyoTestCase extends TestCase
{
    use AdminTestBench, CoreAssertions, CreatesStorefrontProducts;
}
