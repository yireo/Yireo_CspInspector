<?php declare(strict_types=1);

namespace Yireo\CspInspector\Console\Command;

use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\CspInspector\Model\CspHeader;
use Yireo\CspInspector\Util\FetchCspHeader;

abstract class AbstractInspectCommand extends Command
{
    public function __construct(
        private FetchCspHeader $fetchCspHeader,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function addFetchArguments(): void
    {
        $this->addArgument('url', InputArgument::OPTIONAL, 'URL', '/');
        $this->addOption(
            'insecure',
            'k',
            InputOption::VALUE_NONE,
            'Skip TLS certificate verification (for example for self-signed certificates)'
        );
        $this->addOption('follow-redirects', null, InputOption::VALUE_NONE, 'Follow HTTP redirects');
    }

    protected function fetchCspHeader(InputInterface $input, OutputInterface $output): ?CspHeader
    {
        $url = (string)$input->getArgument('url');

        try {
            $cspHeader = $this->fetchCspHeader->fetch(
                $url,
                !$input->getOption('insecure'),
                (bool)$input->getOption('follow-redirects')
            );
        } catch (GuzzleException|RuntimeException|InvalidArgumentException $exception) {
            $output->writeln(
                '<error>Failed to fetch URL ' . $this->escape($url) . ': '
                . $this->escape($exception->getMessage()) . '</error>'
            );

            return null;
        }

        if ($cspHeader->isRedirect()) {
            $output->writeln(sprintf(
                'URL %s redirects (HTTP %d) to %s. Use --follow-redirects to inspect the final URL',
                $this->escape($cspHeader->getUrl()),
                $cspHeader->getStatusCode(),
                $this->escape($cspHeader->getRedirectLocation())
            ));

            return null;
        }

        if (!$cspHeader->hasPolicies()) {
            $output->writeln('No CSP headers found for URL ' . $this->escape($cspHeader->getUrl()));

            return null;
        }

        return $cspHeader;
    }

    protected function escape(string $value): string
    {
        return OutputFormatter::escape($value);
    }
}
