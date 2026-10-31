<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Unit\Console\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\CspInspector\Console\Command\InspectCommand;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Util\FetchCspHeader;

class InspectCommandTest extends TestCase
{
    public function testConfiguration(): void
    {
        $command = new InspectCommand($this->createMock(FetchCspHeader::class));

        $this->assertSame('csp:inspect', $command->getName());
        $this->assertFalse($command->getDefinition()->getArgument('url')->isRequired());
        $this->assertSame('/', $command->getDefinition()->getArgument('url')->getDefault());
    }

    public function testFetchesHomepageByDefault(): void
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->expects($this->once())->method('fetch')->with('/')
            ->willReturn(new CspHeader('https://example.com/', false, []));

        $commandTester = new CommandTester(new InspectCommand($fetchCspHeader));
        $commandTester->execute([]);
    }

    public function testRendersStrictMode(): void
    {
        $commandTester = $this->executeWith(new CspHeader(
            'https://example.com/checkout/cart',
            false,
            ['default-src' => ["'self'"], 'script-src' => ["'self'"]]
        ), 'checkout/cart');

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('https://example.com/checkout/cart', $output);
        $this->assertStringContainsString('Strict Mode', $output);
        $this->assertStringNotContainsString('Reporting Mode', $output);
        $this->assertStringContainsString('default-src', $output);
        $this->assertStringContainsString('script-src', $output);
        $this->assertStringNotContainsString("'self'", $output);
    }

    public function testRendersReportingMode(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/', true, ['img-src' => []]));

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Reporting Mode', $commandTester->getDisplay());
    }

    public function testFailsWithoutCspHeader(): void
    {
        $commandTester = $this->executeWith(null, 'foo');

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('No CSP headers found for URL foo', $commandTester->getDisplay());
    }

    private function executeWith(?CspHeader $cspHeader, string $url = '/'): CommandTester
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->method('fetch')->with($url)->willReturn($cspHeader);

        $commandTester = new CommandTester(new InspectCommand($fetchCspHeader));
        $commandTester->execute(['url' => $url]);

        return $commandTester;
    }
}
