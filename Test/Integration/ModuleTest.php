<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Integration;

use PHPUnit\Framework\TestCase;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsEnabled;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsRegistered;

class ModuleTest extends TestCase
{
    use AssertModuleIsEnabled;
    use AssertModuleIsRegistered;

    public function testIfModuleIsEnabled(): void
    {
        $this->assertModuleIsEnabled('Yireo_CspInspector');
        $this->assertModuleIsRegistered('Yireo_CspInspector');
    }
}
