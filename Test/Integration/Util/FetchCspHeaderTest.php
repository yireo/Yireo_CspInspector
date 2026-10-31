<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Integration\Util;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Util\FetchCspHeader;

class FetchCspHeaderTest extends TestCase
{
    public function testCanBeCreatedByObjectManager(): void
    {
        $this->assertInstanceOf(
            FetchCspHeader::class,
            ObjectManager::getInstance()->get(FetchCspHeader::class)
        );
    }

    public function testFetchesRelativeUrlFromDefaultStoreView(): void
    {
        $history = [];
        $fetchCspHeader = $this->createFetchCspHeader([
            new Response(200, ['Content-Security-Policy-Report-Only' => "script-src 'self' https://cdn.test"]),
        ], $history);

        $cspHeader = $fetchCspHeader->fetch('checkout/cart');

        $store = ObjectManager::getInstance()->get(StoreManagerInterface::class)->getDefaultStoreView();
        $this->assertInstanceOf(Store::class, $store);
        $storeUrl = $store->getUrl();
        $this->assertStringStartsWith('http', $storeUrl);
        $this->assertSame($storeUrl . 'checkout/cart', (string)$history[0]['request']->getUri());

        $this->assertInstanceOf(CspHeader::class, $cspHeader);
        $this->assertSame($storeUrl . 'checkout/cart', $cspHeader->getUrl());
        $this->assertTrue($cspHeader->isReporting());
        $this->assertSame(['script-src' => ["'self'", 'https://cdn.test']], $cspHeader->getPolicies());
    }

    public function testCreatesStrictCspHeader(): void
    {
        $history = [];
        $cspHeader = $this->createFetchCspHeader([
            new Response(200, ['Content-Security-Policy' => "default-src 'self'"]),
        ], $history)->fetch('https://example.com/');

        $this->assertFalse($cspHeader->isReporting());
        $this->assertSame(['default-src' => ["'self'"]], $cspHeader->getPolicies());
    }

    private function createFetchCspHeader(array $responses, array &$history): FetchCspHeader
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));

        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->method('create')->willReturn(new Client(['handler' => $handlerStack]));

        return ObjectManager::getInstance()->create(FetchCspHeader::class, ['clientFactory' => $clientFactory]);
    }
}
