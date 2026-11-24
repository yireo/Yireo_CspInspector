<?php
declare(strict_types=1);

namespace Yireo\CspInspector\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InspectPolicyCommand extends AbstractInspectCommand
{
    protected function configure(): void
    {
        $this->setName('csp:inspect:policy');
        $this->setDescription('Inspect a specific storefront URL for a CSP policy');
        $this->addArgument('policy', InputArgument::REQUIRED, 'Policy (for example: script-src)');
        $this->addFetchArguments();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $policy = (string)$input->getArgument('policy');

        $cspHeader = $this->fetchCspHeader($input, $output);
        if ($cspHeader === null) {
            return Command::FAILURE;
        }

        $policies = $cspHeader->getPolicies();
        $reportOnlyPolicies = $cspHeader->getReportOnlyPolicies();
        $lookup = strtolower($policy);
        if (!array_key_exists($lookup, $policies) && !array_key_exists($lookup, $reportOnlyPolicies)) {
            $output->writeln(
                'Policy "' . $this->escape($policy) . '" not found for URL ' . $this->escape($cspHeader->getUrl())
            );

            return Command::FAILURE;
        }

        if (array_key_exists($lookup, $policies)) {
            $this->renderPolicy($output, $policies[$lookup]);
        }

        if (array_key_exists($lookup, $reportOnlyPolicies)) {
            $output->writeln('Report-Only:');
            $this->renderPolicy($output, $reportOnlyPolicies[$lookup]);
        }

        return Command::SUCCESS;
    }

    private function renderPolicy(OutputInterface $output, array $policyParts): void
    {
        $table = new Table($output);
        foreach ($policyParts as $policyPart) {
            $table->addRow([
                $this->getPolicyType((string)$policyPart),
                $this->escape((string)$policyPart),
            ]);
        }

        $table->render();
    }

    private function getPolicyType(string $value): string
    {
        if (str_contains($value, "'sha256-")) {
            return 'Hash';
        }

        if (str_contains($value, "'")) {
            return 'Generic';
        }

        return 'Domain';
    }
}
