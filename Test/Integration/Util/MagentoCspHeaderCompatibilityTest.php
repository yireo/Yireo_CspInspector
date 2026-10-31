<?php declare(strict_types=1);

namespace Yireo\CspInspector\Test\Integration\Util;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Magento\Csp\Api\CspRendererInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\TestCase\AbstractController;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Util\FetchCspHeader;

/**
 * @magentoAppArea frontend
 */
class MagentoCspHeaderCompatibilityTest extends AbstractController
{
    /**
     * @magentoConfigFixture current_store csp/mode/storefront/report_only 0
     */
    public function testParsesEnforcedMagentoCspHeader(): void
    {
        $headers = $this->getMagentoCspHeaders();
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertArrayNotHasKey('Content-Security-Policy-Report-Only', $headers);

        $cspHeader = $this->fetchWithHeaders($headers);

        $this->assertFalse($cspHeader->isReporting());
        $this->assertPoliciesMatch($headers['Content-Security-Policy'], $cspHeader);
    }

    /**
     * @magentoConfigFixture current_store csp/mode/storefront/report_only 1
     */
    public function testParsesReportOnlyMagentoCspHeader(): void
    {
        $headers = $this->getMagentoCspHeaders();
        $this->assertArrayHasKey('Content-Security-Policy-Report-Only', $headers);
        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);

        $cspHeader = $this->fetchWithHeaders($headers);

        $this->assertTrue($cspHeader->isReporting());
        $this->assertPoliciesMatch($headers['Content-Security-Policy-Report-Only'], $cspHeader);
    }

    private function assertPoliciesMatch(string|array $headerValue, CspHeader $cspHeader): void
    {
        $headerValue = implode(';', (array)$headerValue);
        $policies = $cspHeader->getPolicies();

        $this->assertArrayHasKey('default-src', $policies);
        $this->assertArrayHasKey('script-src', $policies);
        $this->assertContains("'self'", $policies['script-src']);

        foreach ($policies as $policyName => $policyValues) {
            $this->assertMatchesRegularExpression(
                '#(^|;)\s*' . preg_quote($policyName, '#') . '(\s|;|$)#',
                $headerValue
            );

            foreach ($policyValues as $policyValue) {
                $this->assertStringContainsString($policyValue, $headerValue);
            }
        }
    }

    private function getMagentoCspHeaders(): array
    {
        $this->dispatch('/');
        $response = $this->getResponse();
        $this->assertInstanceOf(HttpResponse::class, $response);
        ObjectManager::getInstance()->get(CspRendererInterface::class)->render($response);

        $headers = [];
        foreach ($response->getHeaders() as $header) {
            $name = $header->getFieldName();
            if (in_array(strtolower($name), ['content-security-policy', 'content-security-policy-report-only'])) {
                $headers[$name][] = $header->getFieldValue();
            }
        }

        return $headers;
    }

    private function fetchWithHeaders(array $headers): CspHeader
    {
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, $headers)]));
        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->method('create')->willReturn(new Client(['handler' => $handlerStack]));

        $fetchCspHeader = ObjectManager::getInstance()->create(
            FetchCspHeader::class,
            ['clientFactory' => $clientFactory]
        );

        return $fetchCspHeader->fetch('/');
    }
}
