<?php

namespace Balsama\Dealth\Commands;

use Balsama\Dealth\Helpers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @todo
 * Nothing to see here yet.
 */
class ProcessStats extends Command
{

    protected static $defaultName = 'process-stats';

    private $commits;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->commits = json_decode(file_get_contents(__DIR__ . '/../../data/commits.json'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $io = new SymfonyStyle($input, $output);
        $io->writeln('Processing stats!');


        return self::SUCCESS;
    }

    public function recentNewCreditees($numberOfCreditees = 100)
    {
        $creditees = $this->creditees;
        $arrayObject = new \ArrayObject($creditees);
        $arrayObject->uasort(function ($a, $b) {
            return ($a->firstCommitTimestamp <=> $b->firstCommitTimestamp);
        });

        $sortedCreditees = $arrayObject->getArrayCopy();
        $recent = array_slice($sortedCreditees, -$numberOfCreditees);

        foreach ($recent as $recentCreditee) {
            $firstCommitDate = new \DateTime();
            $firstCommitDate->setTimestamp($recentCreditee->firstCommitTimestamp);
            $recentNewCreditees[] = [
                'name' => $recentCreditee->name,
                'firstCommitDate' => $firstCommitDate->format('Y-m-d'),
                'accountCreatedYear' => $recentCreditee->DOUserCreatedYear,
            ];
        }

        return $recentNewCreditees;
    }

    public function findBigCommits()
    {
        foreach ($this->commits as $commit) {
            $foo = 21;
            if (is_countable($commit->creditees)) {
                if (count($commit->creditees) > 100) {
                    $bigCommits[] = [
                        'date' => $commit->date->date,
                        'creditees' => count($commit->creditees),
                        'issueNumber' => $commit->issueNumber,
                        'commit' => $commit,
                    ];
                }
            }
        }
        return $bigCommits;
    }

    public function executeCrediteesByMonth()
    {
        $months = $this->findNewCrediteesByMonth();
        $months = Helpers::includeArrayKeysInArray($months);
        Helpers::writeToCsv(['month', 'new creditees'], $months, 'creditees-by-month.csv');
    }

    public function findNewCrediteesByMonth()
    {
        $months = $this->initializeMonths();
        foreach ($this->creditees as $creditee) {
            $firstCommitDate = new \DateTime();
            $firstCommitDate->setTimestamp($creditee->firstCommitTimestamp);
            $firstCommitYearMonth = $firstCommitDate->format('Y-m');
            $months[$firstCommitYearMonth]++;
        }
        return $months;
    }

    private function initializeMonths()
    {
        $months = [];
        $date = new \DateTime();
        $date->setTimestamp(946702800);
        while ($date->getTimestamp() < time()) {
            $months[$date->format('Y-m')] = 0;
            $date->add(\DateInterval::createFromDateString('1 month'));
        }
        return $months;
    }

}