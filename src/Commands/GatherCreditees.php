<?php

namespace Balsama\Dealth\Commands;

use Balsama\Dealth\Creditees;
use Balsama\Dealth\Helpers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

class GatherCreditees extends Command
{

    protected static $defaultName = 'gather-creditees';

    private Creditees $creditees;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        parent::configure();
        $processedCommits = (array) json_decode(file_get_contents(__DIR__ . '/../../data/commits.json'));
        $this->creditees = new Creditees($processedCommits);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('Gathering and processing creditees from commits.');

        $progressBar = new ProgressBar($output);

        $uniqueCreditees = $this->creditees->findUniqueCreditees();

        $io->writeln('Validating creditees with Drupal.Org.');
        $validatedCreditees = $this->creditees->validateCreditees($uniqueCreditees, $progressBar);
        $progressBar->finish();

        $io->writeln('Processing creditees.');
        $processedCreditees = $this->creditees->processValidCreditees($validatedCreditees);

        $io->writeln('Exporting results.');
        Helpers::exportToJson($processedCreditees, 'creditees.json');

        $progressBar->finish();
        $io->writeln(PHP_EOL . 'Finished gathering and processing creditees.');

        return self::SUCCESS;
    }

}