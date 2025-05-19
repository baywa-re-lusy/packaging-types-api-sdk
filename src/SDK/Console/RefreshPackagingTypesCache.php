<?php

namespace BayWaReLusy\PackagingTypesAPI\SDK\Console;

use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypesApiClient;
use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeSortField;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'packaging-types-api-sdk:refresh-packaging-types-cache')]
class RefreshPackagingTypesCache extends Command
{
    public function __construct(
        protected PackagingTypesApiClient $packagingTypesApiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Refresh Packaging Types Cache. Packaging types are fetched from the Packaging Types API and ' .
            'written into the Cache.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln(sprintf(
                "[%s] Starting Refresh of Packaging Types Cache...",
                (new \DateTime())->format(\DateTimeInterface::RFC3339)
            ));

            $this->packagingTypesApiClient
                ->setConsole($output)
                ->getPackagingTypes(PackagingTypeSortField::ID);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                (new \DateTime())->format('[c] ') . "Process finished with an error : " . $e->getMessage(),
            );

            $output->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
