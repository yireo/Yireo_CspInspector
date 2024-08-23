<?php
declare(strict_types=1);

namespace Yireo\CspInspector\Console\Command;

use Composer\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\CspInspector\Util\FetchCspHeader;

class InspectPolicyCommand extends Command
{
    public function __construct(
        private FetchCspHeader $fetchCspHeader,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('csp:inspect:policy');
        $this->setDescription('Inspect a specific storefront URL for a CSP policy');
        $this->addArgument('policy', InputArgument::REQUIRED, 'Policy (for example: script-src');
        $this->addArgument('url', InputArgument::OPTIONAL, 'URL', '/');
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $policy = $input->getArgument('policy');
        $url = $input->getArgument('url');

        $cspHeader = $this->fetchCspHeader->fetch($url);
        if (empty($cspHeader)) {
            $output->writeln('No CSP headers found for URL '.$url);

            return Command::FAILURE;
        }

        $policies = $cspHeader->getPolicies();
        if (false === array_key_exists($policy, $policies)) {
            $output->writeln('Policy "'.$policy.'" not found for URL '.$url);
            return Command::FAILURE;
        }

        $table = new Table($output);
        foreach ($policies[$policy] as $policyPart) {
            $table->addRow([
                $this->getPolicyType($policyPart),
                $policyPart,
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function getPolicyType(string $value): string
    {
        if (strstr($value, "'sha256-")) {
            return 'Hash';
        }

        if (strstr($value, "'")) {
            return 'Generic';
        }

        return 'Domain';
    }
}
