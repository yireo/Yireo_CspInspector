<?php declare(strict_types=1);

namespace Yireo\CspInspector\Model;

class CspHeader
{
    public function __construct(
        private string $url = '',
        private bool $reporting = false,
        private array $policies = [],
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
}
