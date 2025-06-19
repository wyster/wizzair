<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WizzMultipassService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
        $result = $this->wizzMultipass->getRoutes();
        foreach ($result as $route) {
            if ($input->getOption('origin')) {
                if (in_array($route['departureStation']['id'], explode(',', $input->getOption('origin')), true)) {
                    dump($route);
                }
            } else {
                dump($route);
            }
        }

        return Command::SUCCESS;
    }
}
