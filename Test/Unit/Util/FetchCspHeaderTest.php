<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Unit\Util;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Model\CspHeaderFactory;
use Yireo\CspInspector\Util\FetchCspHeader;

class FetchCspHeaderTest extends TestCase
{
    private const STORE_URL = 'https://example.com/';

    private array $history = [];

    /**
     * @dataProvider getUrls
     */
    public function testResolvesUrl(string $url, string $expectedUrl): void
    {
        $cspHeader = $this->createFetchCspHeader([new Response(200)])->fetch($url);

        $this->assertSame($expectedUrl, $cspHeader->getUrl());
        $this->assertSame($expectedUrl, (string)$this->history[0]['request']->getUri());
    }

    public static function getUrls(): array
    {
        return [
            ['checkout/cart', 'https://example.com/checkout/cart'],
            ['/', 'https://example.com/'],
            ['?foo=bar', 'https://example.com/?foo=bar'],
            ['http://other.test/foo', 'http://other.test/foo'],
            ['https://other.test/foo', 'https://other.test/foo'],
            ['https://other.test//', 'https://other.test/'],
        ];
    }

    public function testDoesNotUseStoreManagerForAbsoluteUrl(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getDefaultStoreView');

        $this->createFetchCspHeader([new Response(200)], $storeManager)->fetch('https://other.test/');
    }

    public function testTreatsUppercaseSchemeAsAbsoluteUrl(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getDefaultStoreView');

        $this->createFetchCspHeader([new Response(200)], $storeManager)->fetch('HTTPS://other.test/foo');

        $this->assertSame('https://other.test/foo', (string)$this->history[0]['request']->getUri());
    }

    public function testFailsWithoutDefaultStoreView(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->createFetchCspHeader([new Response(200)], $storeManager)->fetch('/');
    }

    public function testSendsGetRequestWithSafeDefaults(): void
    {
        $this->createFetchCspHeader([new Response(200)])->fetch('/');

        $this->assertCount(1, $this->history);
        $options = $this->history[0]['options'];
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        $this->assertTrue($options['verify']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertFalse($options['http_errors']);
        $this->assertGreaterThan(0, $options['timeout']);
        $this->assertGreaterThan(0, $options['connect_timeout']);
    }

    public function testCanDisableCertificateVerification(): void
    {
        $this->createFetchCspHeader([new Response(200)])->fetch('/', false);

        $this->assertFalse($this->history[0]['options']['verify']);
    }

    public function testDoesNotFollowRedirectsByDefault(): void
    {
        $cspHeader = $this->createFetchCspHeader([
            new Response(302, ['Location' => 'https://other.test/login']),
            new Response(200, ['Content-Security-Policy' => "default-src 'self'"]),
        ])->fetch('/');

        $this->assertCount(1, $this->history);
        $this->assertTrue($cspHeader->isRedirect());
        $this->assertSame(302, $cspHeader->getStatusCode());
        $this->assertSame('https://other.test/login', $cspHeader->getRedirectLocation());
        $this->assertSame('https://example.com/', $cspHeader->getUrl());
        $this->assertFalse($cspHeader->hasPolicies());
    }

    public function testFollowsRedirectsAndReportsFinalUrl(): void
    {
        $cspHeader = $this->createFetchCspHeader([
            new Response(302, ['Location' => 'https://example.com/final']),
            new Response(200, ['Content-Security-Policy' => "default-src 'self'"]),
        ])->fetch('/', true, true);

        $this->assertCount(2, $this->history);
        $this->assertFalse($cspHeader->isRedirect());
        $this->assertSame(200, $cspHeader->getStatusCode());
        $this->assertSame('https://example.com/final', $cspHeader->getUrl());
        $this->assertSame(['default-src' => ["'self'"]], $cspHeader->getPolicies());
    }

    public function testReportsErrorStatusInsteadOfThrowing(): void
    {
        $cspHeader = $this->createFetchCspHeader([
            new Response(404, ['Content-Security-Policy' => "default-src 'none'"], 'secret body'),
        ])->fetch('/');

        $this->assertSame(404, $cspHeader->getStatusCode());
        $this->assertSame(['default-src' => ["'none'"]], $cspHeader->getPolicies());
    }

    /**
     * @dataProvider getResponses
     */
    public function testParsesHeaders(
        array $headers,
        bool $expectedReporting,
        array $expectedPolicies,
        array $expectedReportOnlyPolicies = []
    ): void {
        $cspHeader = $this->createFetchCspHeader([new Response(200, $headers)])->fetch('/');

        $this->assertSame($expectedReporting, $cspHeader->isReporting());
        $this->assertSame($expectedPolicies, $cspHeader->getPolicies());
        $this->assertSame($expectedReportOnlyPolicies, $cspHeader->getReportOnlyPolicies());
    }

    public static function getResponses(): array
    {
        return [
            'no header' => [
                [],
                false,
                [],
            ],
            'report-only header' => [
                ['Content-Security-Policy-Report-Only' => "default-src 'self'; script-src 'self' https://cdn.test"],
                true,
                ['default-src' => ["'self'"], 'script-src' => ["'self'", 'https://cdn.test']],
            ],
            'enforced header' => [
                ['Content-Security-Policy' => "script-src 'self' 'sha256-abc='"],
                false,
                ['script-src' => ["'self'", "'sha256-abc='"]],
            ],
            'enforced header and report-only header are both kept' => [
                [
                    'Content-Security-Policy-Report-Only' => "img-src 'self'",
                    'Content-Security-Policy' => "script-src 'none'",
                ],
                false,
                ['script-src' => ["'none'"]],
                ['img-src' => ["'self'"]],
            ],
            'multiple header values' => [
                ['Content-Security-Policy' => ["default-src 'self'", "script-src https://cdn.test"]],
                false,
                ['default-src' => ["'self'"], 'script-src' => ['https://cdn.test']],
            ],
            'comma-separated policies' => [
                ['Content-Security-Policy' => "default-src 'self', script-src https://cdn.test"],
                false,
                ['default-src' => ["'self'"], 'script-src' => ['https://cdn.test']],
            ],
            'first directive wins' => [
                ['Content-Security-Policy' => "script-src 'none'; script-src *"],
                false,
                ['script-src' => ["'none'"]],
            ],
            'repeated whitespace' => [
                ['Content-Security-Policy' => "script-src   'self' \t https://cdn.test  "],
                false,
                ['script-src' => ["'self'", 'https://cdn.test']],
            ],
            'empty directives' => [
                ['Content-Security-Policy' => "default-src 'self';; ; upgrade-insecure-requests;"],
                false,
                ['default-src' => ["'self'"], 'upgrade-insecure-requests' => []],
            ],
        ];
    }

    private function createFetchCspHeader(
        array $responses,
        ?StoreManagerInterface $storeManager = null
    ): FetchCspHeader {
        $this->history = [];
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($this->history));

        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->method('create')->willReturn(new Client(['handler' => $handlerStack]));

        $cspHeaderFactory = $this->createMock(CspHeaderFactory::class);
        $cspHeaderFactory->method('create')->willReturnCallback(fn (array $data) => new CspHeader(...$data));

        if ($storeManager === null) {
            $store = $this->createMock(Store::class);
            $store->method('getUrl')->willReturn(self::STORE_URL);
            $storeManager = $this->createMock(StoreManagerInterface::class);
            $storeManager->method('getDefaultStoreView')->willReturn($store);
        }

        return new FetchCspHeader($storeManager, $clientFactory, $cspHeaderFactory);
    }
}
