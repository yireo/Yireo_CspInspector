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
    }

    public function testGetters(): void
    {
        $policies = ['script-src' => ["'self'", 'https://example.com']];
        $cspHeader = new CspHeader('https://example.com/', true, $policies);

        $this->assertSame('https://example.com/', $cspHeader->getUrl());
        $this->assertTrue($cspHeader->isReporting());
        $this->assertSame($policies, $cspHeader->getPolicies());
    }
}
