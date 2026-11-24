<?php declare(strict_types=1);

namespace Yireo\CspInspector\Util;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RedirectMiddleware;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Model\CspHeaderFactory;

class FetchCspHeader
{
    private const TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private StoreManagerInterface $storeManager,
        private ClientFactory $clientFactory,
        private CspHeaderFactory $cspHeaderFactory
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws RuntimeException
     */
    public function fetch(string $url, bool $verifyTls = true, bool $followRedirects = false): CspHeader
    {
        $url = $this->resolveUrl($url);

        $client = $this->clientFactory->create();
        $response = $client->request('GET', $url, [
            'verify' => $verifyTls,
            'allow_redirects' => $followRedirects
                ? ['max' => self::MAX_REDIRECTS, 'track_redirects' => true]
                : false,
            'http_errors' => false,
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ]);

        $redirectHistory = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
        if (!empty($redirectHistory)) {
            $url = (string)end($redirectHistory);
        }

        $policies = $this->parsePolicies($response->getHeader('Content-Security-Policy'));
        $reportOnlyPolicies = $this->parsePolicies($response->getHeader('Content-Security-Policy-Report-Only'));
        $reporting = empty($policies) && !empty($reportOnlyPolicies);

        return $this->cspHeaderFactory->create([
            'url' => $url,
            'reporting' => $reporting,
            'policies' => $reporting ? $reportOnlyPolicies : $policies,
            'reportOnlyPolicies' => $reporting ? [] : $reportOnlyPolicies,
            'statusCode' => $response->getStatusCode(),
            'redirectLocation' => $response->getHeaderLine('Location'),
        ]);
    }

    private function resolveUrl(string $url): string
    {
        if (!preg_match('#^https?://#i', $url)) {
            $store = $this->storeManager->getDefaultStoreView();
            if ($store === null) {
                throw new RuntimeException('No default store view found to resolve a relative URL');
            }

            $url = $store->getUrl() . $url;
        }

        if (preg_match('#//$#', $url)) {
            $url = rtrim($url, '/') . '/';
        }

        return $url;
    }

    private function parsePolicies(array $headerValues): array
    {
        $policies = [];
        foreach ($headerValues as $headerValue) {
            foreach (preg_split('/[;,]/', (string)$headerValue) as $directive) {
                $directiveParts = preg_split('/\s+/', $directive, -1, PREG_SPLIT_NO_EMPTY);
                $directiveName = array_shift($directiveParts);
                if (empty($directiveName)) {
                    continue;
                }

                $policies[strtolower($directiveName)] ??= $directiveParts;
            }
        }

        return $policies;
    }
}
