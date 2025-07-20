<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Exception\RouteNotAvailableException;
use App\Service\WizzMultipassService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'check-availability',
)]
class CheckAvailabilityCommand extends Command
{
    public function __construct(
        private readonly WizzMultipassService $wizzMultipass,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('origin', null, InputOption::VALUE_REQUIRED, 'Origin')
            ->addOption('destination', null, InputOption::VALUE_REQUIRED, 'Destination')
            ->addOption('departure', null, InputOption::VALUE_REQUIRED, 'Departure')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $originCode = (string) $input->getOption('origin');
        if (!$originCode) {
            $output->writeln('<error>Origin is required.</error>');

            return Command::FAILURE;
        }
        $destination = (string) $input->getOption('destination');
        if (!$destination) {
            $output->writeln('<error>Destination is required.</error>');

            return Command::FAILURE;
        }
        $departureDate = (string) $input->getOption('departure');
        if (!$departureDate) {
            $output->writeln('<error>Departure is required.</error>');

            return Command::FAILURE;
        }

        $date = new \DateTimeImmutable($departureDate);
        $io->writeln(sprintf('Date: %s', $date->format('Y-m-d')));

        $table = new Table($output);
        $table->setHeaders(['Departure', 'Arrival', 'Departure time', 'Arrival time']);

        $destinations = explode(',', $destination);
        foreach ($destinations as $destinationCode) {
            try {
                $result = $this->wizzMultipass->getAvailability(
                    $originCode,
                    $destinationCode,
                    $date
                );
                foreach ($result['flightsOutbound'] as $item) {
                    $table->addRow([
                        $item['departureStationCode'],
                        sprintf('%s (%s)', $item['arrivalStationText'], $item['arrivalStationCode']),
                        $item['departure'],
                        $item['arrival'],
                    ]);
                }
            } catch (RouteNotAvailableException) {
                $table->addRow([
                    $originCode,
                    $destinationCode,
                    'Route is not available'
                ]);
            }
        }

        $table->render();

        return Command::SUCCESS;
    }
}
