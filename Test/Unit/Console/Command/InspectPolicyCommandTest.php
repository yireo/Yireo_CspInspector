<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Unit\Console\Command;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\CspInspector\Console\Command\InspectPolicyCommand;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Util\FetchCspHeader;

class InspectPolicyCommandTest extends TestCase
{
    public function testConfiguration(): void
    {
        $command = new InspectPolicyCommand($this->createMock(FetchCspHeader::class));
        $definition = $command->getDefinition();

        $this->assertSame('csp:inspect:policy', $command->getName());
        $this->assertTrue($definition->getArgument('policy')->isRequired());
        $this->assertFalse($definition->getArgument('url')->isRequired());
        $this->assertSame('/', $definition->getArgument('url')->getDefault());
        $this->assertTrue($definition->hasOption('insecure'));
        $this->assertTrue($definition->hasOption('follow-redirects'));
    }

    public function testPassesOptions(): void
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->expects($this->once())->method('fetch')->with('/', false, true)
            ->willReturn(new CspHeader('https://example.com/', false, ['script-src' => []]));

        $commandTester = new CommandTester(new InspectPolicyCommand($fetchCspHeader));
        $commandTester->execute(['policy' => 'script-src', '-k' => true, '--follow-redirects' => true]);
    }

    public function testRendersPolicyValuesWithType(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/', false, [
            'script-src' => ["'self'", "'unsafe-inline'", "'sha256-abc='", '*.example.com', 'https://cdn.test'],
            'img-src' => ['https://img.test'],
        ]), 'script-src');

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $rows = $this->getRows($commandTester->getDisplay());
        $this->assertSame([
            ['Generic', "'self'"],
            ['Generic', "'unsafe-inline'"],
            ['Hash', "'sha256-abc='"],
            ['Domain', '*.example.com'],
            ['Domain', 'https://cdn.test'],
        ], $rows);
    }

    public function testRendersReportOnlyPolicyValues(): void
    {
        $commandTester = $this->executeWith(new CspHeader(
            'https://example.com/',
            false,
            ['script-src' => ["'self'"]],
            ['img-src' => ['https://img.test']]
        ), 'img-src');

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Report-Only', $output);
        $this->assertSame([['Domain', 'https://img.test']], $this->getRows($output));
    }

    public function testEscapesRemoteValues(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/', false, [
            'script-src' => ['<href=https://evil.test>https://cdn.test</>'],
        ]), 'script-src');

        $this->assertStringContainsString(
            '<href=https://evil.test>https://cdn.test</>',
            $commandTester->getDisplay()
        );
    }

    public function testEscapesPolicyArgument(): void
    {
        $commandTester = $this->executeWith(
            new CspHeader('https://example.com/', false, ['script-src' => []]),
            '<fg=red>foo</>'
        );

        $this->assertStringContainsString('Policy "<fg=red>foo</>" not found', $commandTester->getDisplay());
    }

    public function testFailsForUnknownPolicy(): void
    {
        $commandTester = $this->executeWith(
            new CspHeader('https://example.com/checkout/cart', false, ['script-src' => ["'self'"]]),
            'foo-src',
            'checkout/cart'
        );

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Policy "foo-src" not found for URL https://example.com/checkout/cart',
            $commandTester->getDisplay()
        );
    }

    public function testReportsRedirect(): void
    {
        $commandTester = $this->executeWith(
            new CspHeader('https://example.com/', false, [], [], 301, 'https://example.com/new'),
            'script-src'
        );

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('https://example.com/new', $commandTester->getDisplay());
    }

    public function testFailsWithoutCspHeader(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/'), 'script-src');

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('No CSP headers found for URL https://example.com/', $commandTester->getDisplay());
    }

    public function testFailsGracefullyOnFetchError(): void
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->method('fetch')->willThrowException(new RuntimeException('No default store view'));

        $commandTester = new CommandTester(new InspectPolicyCommand($fetchCspHeader));
        $commandTester->execute(['policy' => 'script-src']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Failed to fetch URL /: No default store view', $commandTester->getDisplay());
    }

    private function executeWith(CspHeader $cspHeader, string $policy, string $url = '/'): CommandTester
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->method('fetch')->with($url)->willReturn($cspHeader);

        $commandTester = new CommandTester(new InspectPolicyCommand($fetchCspHeader));
        $commandTester->execute(['policy' => $policy, 'url' => $url]);

        return $commandTester;
    }

    private function getRows(string $output): array
    {
        $rows = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line, " \f\n\r\t\v\x00");
            if (!str_starts_with($line, '|')) {
                continue;
            }

            $cells = array_map(fn (string $cell) => trim($cell, " \f\n\r\t\v\x00"), explode('|', trim($line, '|')));
            $rows[] = $cells;
        }

        return $rows;
    }
}
