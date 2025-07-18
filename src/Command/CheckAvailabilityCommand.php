<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Exception\RouteNotAvailableException;
use App\Service\WizzMultipassService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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

        $origin = (string) $input->getOption('origin');
        if (!$origin) {
            $output->writeln('<error>Origin is required.</error>');

            return Command::FAILURE;
        }
        $destination = (string) $input->getOption('destination');
        if (!$destination) {
            $output->writeln('<error>Destination is required.</error>');

            return Command::FAILURE;
        }
        $departure = (string) $input->getOption('departure');
        if (!$departure) {
            $output->writeln('<error>Departure is required.</error>');

            return Command::FAILURE;
        }

        try {
            $date = new \DateTimeImmutable($departure);
            $io->writeln(sprintf('Date: %s', $date->format('Y-m-d')));
            $result = $this->wizzMultipass->getAvailability(
                $origin,
                $destination,
                $date
            );
            dump($result);
        } catch (RouteNotAvailableException) {
            $io->error('Route not available');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
