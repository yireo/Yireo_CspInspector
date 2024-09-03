<?php declare(strict_types=1);

namespace Yireo\CspInspector\Util;

use GuzzleHttp\ClientFactory;
use Magento\Store\Model\StoreManager;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Model\CspHeaderFactory;

class FetchCspHeader
{
    public function __construct(
        private StoreManager $storeManager,
        private ClientFactory $clientFactory,
        private CspHeaderFactory $cspHeaderFactory
    ) {
    }

    public function fetch(string $url): ?CspHeader
    {
        $url = $this->storeManager->getDefaultStoreView()->getUrl().$url;
        if (preg_match('#//$#', $url)) {
            $url = rtrim($url, '/').'/';
        }

        $client = $this->clientFactory->create();
        $response = $client->request(
            'GET', $url, ['verify' => false]
        );

        // @todo: Prevent redirects
        // @todo: Allow for inspecting the checkout as well (with cart-items)

        $cspHeaderData = [];
        $cspHeaderData['url'] = $url;
        $headerValues = [];

        if ($response->hasHeader('Content-Security-Policy-Report-Only')) {
            $cspHeaderData['reporting'] = 1;
            $headerValues = $response->getHeader('Content-Security-Policy-Report-Only');
        }

        if ($response->hasHeader('Content-Security-Policy')) {
            $cspHeaderData['reporting'] = 0;
            $headerValues = $response->getHeader('Content-Security-Policy');
        }

        foreach ($headerValues as $headerValue) {
            foreach (explode(';', $headerValue) as $headerValuePart) {
                $policyParts = explode(' ', trim($headerValuePart));
                $policyName = array_shift($policyParts);
                if (empty($policyName)) {
                    continue;
                }

                $cspHeaderData['policies'][$policyName] = $policyParts;
            }
        }

        return $this->cspHeaderFactory->create($cspHeaderData);
    }
}
