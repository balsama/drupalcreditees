<?php

namespace Balsama\Dealth\Commands;

use Balsama\Dealth\Commits;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

class GatherCommits extends Command
{

    protected static $defaultName = 'gather:commits';
    protected static $defaultDescription = 'Gathers commits from repos in config.yml and outputs the results to a json file.';

    private Commits $repos;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        parent::configure();
        $this->repos = new Commits();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('Gathering and processing commits from repos.');

        $progressBar = new ProgressBar($output);
        $progressBar->start(3);

        $this->repos->gatherCommits();
        $progressBar->advance();

        $this->repos->processCommits();
        $progressBar->advance();

        $this->repos->outputCommitsToFile();
        $progressBar->advance();

        $progressBar->finish();
        $io->writeln(PHP_EOL . 'Finished gathering and processing commits.');

        return self::SUCCESS;
    }

}