<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Yireo\CspInspector\Model\CspHeader;

class CspHeaderTest extends TestCase
{
    public function testDefaults(): void
    {
        $cspHeader = new CspHeader();

        $this->assertSame('', $cspHeader->getUrl());
        $this->assertFalse($cspHeader->isReporting());
        $this->assertSame([], $cspHeader->getPolicies());
        $this->assertSame([], $cspHeader->getReportOnlyPolicies());
        $this->assertSame(200, $cspHeader->getStatusCode());
        $this->assertSame('', $cspHeader->getRedirectLocation());
        $this->assertFalse($cspHeader->isRedirect());
        $this->assertFalse($cspHeader->hasPolicies());
    }

    public function testGetters(): void
    {
        $policies = ['script-src' => ["'self'", 'https://example.com']];
        $reportOnlyPolicies = ['img-src' => ["'self'"]];
        $cspHeader = new CspHeader('https://example.com/', true, $policies, $reportOnlyPolicies, 302, '/foo');

        $this->assertSame('https://example.com/', $cspHeader->getUrl());
        $this->assertTrue($cspHeader->isReporting());
        $this->assertSame($policies, $cspHeader->getPolicies());
        $this->assertSame($reportOnlyPolicies, $cspHeader->getReportOnlyPolicies());
        $this->assertSame(302, $cspHeader->getStatusCode());
        $this->assertSame('/foo', $cspHeader->getRedirectLocation());
        $this->assertTrue($cspHeader->isRedirect());
        $this->assertTrue($cspHeader->hasPolicies());
    }

    public function testHasPoliciesWithReportOnlyPoliciesOnly(): void
    {
        $this->assertTrue((new CspHeader('', false, [], ['img-src' => []]))->hasPolicies());
    }

    public function testIsNoRedirectWithoutLocation(): void
    {
        $this->assertFalse((new CspHeader('', false, [], [], 304))->isRedirect());
    }
}
