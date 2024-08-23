<?php
declare(strict_types=1);

namespace Yireo\CspInspector\Console\Command;

use Composer\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\CspInspector\Util\FetchCspHeader;

class InspectCommand extends Command
{
    public function __construct(
        private FetchCspHeader $fetchCspHeader,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('csp:inspect');
        $this->setDescription('Inspect a specific storefront URL for all CSP headers');
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
        $url = $input->getArgument('url');

        $cspHeader = $this->fetchCspHeader->fetch($url);
        if (empty($cspHeader)) {
            $output->writeln('No CSP headers found for URL '.$url);

            return Command::FAILURE;
        }

        $table = new Table($output);

        $table->addRow([
            'URL',
            $cspHeader->getUrl(),
        ]);

        $table->addRow([
            'Mode',
            $cspHeader->isReporting() ? 'Reporting Mode' : 'Strict Mode',
        ]);

        $policies = array_keys($cspHeader->getPolicies());
        $table->addRow([
            'Policies',
            implode("\n", $policies)
        ]);

        $table->render();

        return Command::SUCCESS;
    }
}
