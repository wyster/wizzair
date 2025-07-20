<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WizzMultipassService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'routes',
)]
class RoutesCommand extends Command
{
    public function __construct(
        private readonly WizzMultipassService $wizzMultipass,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('origin', null, InputOption::VALUE_OPTIONAL, 'Origin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Departure', 'Arrival']);

        $routes = $this->wizzMultipass->getRoutes();
        $output->writeln(sprintf('Total count: %s', count($routes)));
        foreach ($routes as $route) {
            if ($input->getOption('origin')) {
                if (!in_array($route['departureStation']['id'], explode(',', $input->getOption('origin')), true)) {
                    continue;
                }
            }

            foreach ($route['arrivalStations'] as $arrival) {
                $table->addRow([
                    sprintf('%s (%s)', $route['departureStation']['name'], $route['departureStation']['id']),
                    sprintf('%s (%s)', $arrival['name'], $arrival['id']),
                ]);
            }
        }

        $table->render();

        return Command::SUCCESS;
    }
}
