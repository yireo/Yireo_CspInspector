<?php declare(strict_types=1);

namespace Yireo\CspInspector\Model;

class CspHeader
{
    public function __construct(
        private string $url = '',
        private bool $reporting = false,
        private array $policies = [],
        private array $reportOnlyPolicies = [],
        private int $statusCode = 200,
        private string $redirectLocation = '',
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isReporting(): bool
    {
        return $this->reporting;
    }

    public function getPolicies(): array
    {
        return $this->policies;
    }

    public function getReportOnlyPolicies(): array
    {
        return $this->reportOnlyPolicies;
    }

    public function hasPolicies(): bool
    {
        return !empty($this->policies) || !empty($this->reportOnlyPolicies);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRedirectLocation(): string
    {
        return $this->redirectLocation;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400 && $this->redirectLocation !== '';
    }
}
