<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Unit\Util;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use PHPUnit\Framework\TestCase;
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
        $storeManager = $this->createMock(StoreManager::class);
        $storeManager->expects($this->never())->method('getDefaultStoreView');

        $this->createFetchCspHeader([new Response(200)], $storeManager)->fetch('https://other.test/');
    }

    public function testSendsGetRequestWithoutCertificateVerification(): void
    {
        $this->createFetchCspHeader([new Response(200)])->fetch('/');

        $this->assertCount(1, $this->history);
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        $this->assertFalse($this->history[0]['options']['verify']);
    }

    /**
     * @dataProvider getResponses
     */
    public function testParsesHeaders(array $headers, bool $expectedReporting, array $expectedPolicies): void
    {
        $cspHeader = $this->createFetchCspHeader([new Response(200, $headers)])->fetch('/');

        $this->assertSame($expectedReporting, $cspHeader->isReporting());
        $this->assertSame($expectedPolicies, $cspHeader->getPolicies());
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
            'enforced header wins over report-only header' => [
                [
                    'Content-Security-Policy-Report-Only' => "img-src 'self'",
                    'Content-Security-Policy' => "script-src 'none'",
                ],
                false,
                ['script-src' => ["'none'"]],
            ],
            'multiple header values' => [
                ['Content-Security-Policy' => ["default-src 'self'", "script-src https://cdn.test"]],
                false,
                ['default-src' => ["'self'"], 'script-src' => ['https://cdn.test']],
            ],
            'empty directives' => [
                ['Content-Security-Policy' => "default-src 'self';; ; upgrade-insecure-requests;"],
                false,
                ['default-src' => ["'self'"], 'upgrade-insecure-requests' => []],
            ],
        ];
    }

    private function createFetchCspHeader(array $responses, ?StoreManager $storeManager = null): FetchCspHeader
    {
        $this->history = [];
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($this->history));

        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->method('create')->willReturn(new Client(['handler' => $handlerStack]));

        $cspHeaderFactory = $this->createMock(CspHeaderFactory::class);
        $cspHeaderFactory->method('create')->willReturnCallback(fn (array $data) => new CspHeader(
            $data['url'] ?? '',
            (bool)($data['reporting'] ?? false),
            $data['policies'] ?? []
        ));

        if ($storeManager === null) {
            $store = $this->createMock(Store::class);
            $store->method('getUrl')->willReturn(self::STORE_URL);
            $storeManager = $this->createMock(StoreManager::class);
            $storeManager->method('getDefaultStoreView')->willReturn($store);
        }

        return new FetchCspHeader($storeManager, $clientFactory, $cspHeaderFactory);
    }
}
