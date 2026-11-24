<?php
declare(strict_types=1);

namespace Yireo\CspInspector\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InspectCommand extends AbstractInspectCommand
{
    protected function configure(): void
    {
        $this->setName('csp:inspect');
        $this->setDescription('Inspect a specific storefront URL for all CSP headers');
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
        $cspHeader = $this->fetchCspHeader($input, $output);
        if ($cspHeader === null) {
            return Command::FAILURE;
        }

        $table = new Table($output);

        $table->addRow([
            'URL',
            $this->escape($cspHeader->getUrl()),
        ]);

        if ($cspHeader->getStatusCode() !== 200) {
            $table->addRow([
                'Status',
                (string)$cspHeader->getStatusCode(),
            ]);
        }

        $table->addRow([
            'Mode',
            $cspHeader->isReporting() ? 'Reporting Mode' : 'Strict Mode',
        ]);

        $table->addRow([
            'Policies',
            $this->renderPolicyNames($cspHeader->getPolicies()),
        ]);

        if (!empty($cspHeader->getReportOnlyPolicies())) {
            $table->addRow([
                'Report-Only Policies',
                $this->renderPolicyNames($cspHeader->getReportOnlyPolicies()),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function renderPolicyNames(array $policies): string
    {
        return implode("\n", array_map(fn ($name) => $this->escape((string)$name), array_keys($policies)));
    }
}
