<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Unit\Console\Command;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
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
        $definition = $command->getDefinition();

        $this->assertSame('csp:inspect', $command->getName());
        $this->assertFalse($definition->getArgument('url')->isRequired());
        $this->assertSame('/', $definition->getArgument('url')->getDefault());
        $this->assertTrue($definition->hasOption('insecure'));
        $this->assertSame('k', $definition->getOption('insecure')->getShortcut());
        $this->assertTrue($definition->hasOption('follow-redirects'));
    }

    public function testFetchesHomepageSecurelyByDefault(): void
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->expects($this->once())->method('fetch')->with('/', true, false)
            ->willReturn(new CspHeader('https://example.com/', false, ['default-src' => []]));

        $commandTester = new CommandTester(new InspectCommand($fetchCspHeader));
        $commandTester->execute([]);
    }

    public function testPassesOptions(): void
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->expects($this->once())->method('fetch')->with('/', false, true)
            ->willReturn(new CspHeader('https://example.com/', false, ['default-src' => []]));

        $commandTester = new CommandTester(new InspectCommand($fetchCspHeader));
        $commandTester->execute(['--insecure' => true, '--follow-redirects' => true]);
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
        $this->assertStringNotContainsString('Report-Only Policies', $output);
        $this->assertStringNotContainsString('Status', $output);
    }

    public function testRendersReportingMode(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/', true, ['img-src' => []]));

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Reporting Mode', $commandTester->getDisplay());
    }

    public function testRendersReportOnlyPoliciesNextToEnforcedPolicies(): void
    {
        $commandTester = $this->executeWith(new CspHeader(
            'https://example.com/',
            false,
            ['script-src' => []],
            ['img-src' => []]
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Strict Mode', $output);
        $this->assertStringContainsString('Report-Only Policies', $output);
        $this->assertStringContainsString('img-src', $output);
    }

    public function testRendersNonSuccessStatus(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/', false, ['img-src' => []], [], 404));

        $this->assertMatchesRegularExpression('/Status\s*\|\s*404/', $commandTester->getDisplay());
    }

    public function testReportsRedirect(): void
    {
        $commandTester = $this->executeWith(
            new CspHeader('https://example.com/', false, [], [], 302, 'https://example.com/login')
        );

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('302', $output);
        $this->assertStringContainsString('https://example.com/login', $output);
        $this->assertStringContainsString('--follow-redirects', $output);
    }

    public function testEscapesRemoteValues(): void
    {
        $commandTester = $this->executeWith(new CspHeader(
            'https://example.com/<href=https://evil.test>x</>',
            false,
            ['<href=https://evil.test>script-src</>' => []]
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('<href=https://evil.test>script-src</>', $output);
        $this->assertStringContainsString('https://example.com/<href=https://evil.test>x</>', $output);
    }

    public function testFailsWithoutCspHeader(): void
    {
        $commandTester = $this->executeWith(new CspHeader('https://example.com/foo'), 'foo');

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('No CSP headers found for URL https://example.com/foo', $commandTester->getDisplay());
    }

    public function testFailsGracefullyOnFetchError(): void
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->method('fetch')->willThrowException(
            new ConnectException('Could not resolve <fg=red>host</>', new Request('GET', 'https://foo.test'))
        );

        $commandTester = new CommandTester(new InspectCommand($fetchCspHeader));
        $commandTester->execute(['url' => 'https://foo.test']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Failed to fetch URL https://foo.test: Could not resolve <fg=red>host</>',
            $commandTester->getDisplay()
        );
    }

    private function executeWith(CspHeader $cspHeader, string $url = '/'): CommandTester
    {
        $fetchCspHeader = $this->createMock(FetchCspHeader::class);
        $fetchCspHeader->method('fetch')->with($url)->willReturn($cspHeader);

        $commandTester = new CommandTester(new InspectCommand($fetchCspHeader));
        $commandTester->execute(['url' => $url]);

        return $commandTester;
    }
}
