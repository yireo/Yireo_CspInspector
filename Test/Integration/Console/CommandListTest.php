<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Integration\Console;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Console\CommandListInterface;
use PHPUnit\Framework\TestCase;
use Yireo\CspInspector\Console\Command\InspectCommand;
use Yireo\CspInspector\Console\Command\InspectPolicyCommand;

class CommandListTest extends TestCase
{
    /**
     * @dataProvider getCommands
     */
    public function testCommandIsRegistered(string $key, string $class, string $name): void
    {
        $commands = ObjectManager::getInstance()->get(CommandListInterface::class)->getCommands();

        $this->assertArrayHasKey($key, $commands);
        $this->assertInstanceOf($class, $commands[$key]);
        $this->assertSame($name, $commands[$key]->getName());
    }

    public static function getCommands(): array
    {
        return [
            ['yireo_cspinspector_inspect', InspectCommand::class, 'csp:inspect'],
            ['yireo_cspinspector_inspect_policy', InspectPolicyCommand::class, 'csp:inspect:policy'],
        ];
    }
}
