<?php

namespace Balsama\Dealth\Commands;

use Balsama\Dealth\Helpers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProcessStats extends Command
{

    protected static $defaultName = 'process:stats';
    protected static $defaultDescription = 'Processes the commits and creditees json files into various CSVs for graphing.';

    private $commits;
    private $creditees;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->commits = (array) json_decode(file_get_contents(__DIR__ . '/../../data/commits.json'));
        $this->creditees = (array) json_decode(file_get_contents(__DIR__ . '/../../data/creditees.json'));
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption('generate', 'g', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Processing stats!');

        $options = $input->getOptions();
        if (!$options['generate']) {
            $io->error('You must provide the --generate option with a callable value.');
            return self::INVALID;
        }

        if (is_callable([$this, $options['generate']])) {
            $this->{$options['generate']}();
        }
        else {
            $io->error('The value provided to the --generate option must be a callable method.');
            return self::INVALID;
        }

        return self::SUCCESS;
    }

    public function recentNewCreditees($numberOfCreditees = 100)
    {
        $creditees = $this->creditees;
        uasort($creditees, function ($a, $b) {
            return ($a->firstCommitTimestamp <=> $b->firstCommitTimestamp);
        });

        $recent = array_slice($creditees, -$numberOfCreditees);

        foreach ($recent as $recentCreditee) {
            $firstCommitDate = new \DateTime();
            $firstCommitDate->setTimestamp($recentCreditee->firstCommitTimestamp);
            $accountCreatedDate = new \DateTime();
            $accountCreatedDate->setTimestamp($recentCreditee->accountCreatedTimestamp);
            $timeBetween = date_diff($accountCreatedDate, $firstCommitDate);
            $recentNewCreditees[] = [
                'name' => $recentCreditee->name,
                'firstCommitDate' => $firstCommitDate->format('Y-m-d'),
                'accountCreatedDate' => $accountCreatedDate->format('Y-m-d'),
                'secondsBetweenAccountCreationAndFirstCommit' => ($firstCommitDate->getTimestamp() - $accountCreatedDate->getTimestamp()),
                'formattedTimeBetweenAccountCreationAndFirstCommit' => $timeBetween->format('%y years %m months and %d days')
            ];
        }

        $headers = ['username', 'first commit date', 'account created date', 'seconds between account creation and time of first commit', 'formatted account age at time of first commit'];
        Helpers::writeToCsv($headers, $recentNewCreditees, 'recent-new-creditees.csv');
        return $recentNewCreditees;
    }

    public function crediteesByMonth()
    {
        $months = $this->initializeMonthsWithCustomKeys(['uniqueCreditees' => []]);
        foreach ($this->commits as $commit) {
            $commitMonth = substr($commit->date->date, 0, 7);
            foreach ($commit->creditees as $creditee) {
                $months[$commitMonth]['uniqueCreditees'][$creditee] = 0;
            }
        }
        foreach ($months as $month) {
            $count = count($month['uniqueCreditees']);
            $months[$month[0]]['uniqueCreditees'] = $count;
        }
        Helpers::writeToCsv(['month', 'creditees'], $months, 'all-creditees-by-month.csv');
    }

    public function issuesClosedPerMonth()
    {
        $months = $this->initializeMonthsWithCustomKeys(['uniqueIssuesClosed' => []]);
        foreach ($this->commits as $commit) {
            $commitMonth = substr($commit->date->date, 0, 7);
            $months[$commitMonth]['uniqueIssuesClosed'][$commit->issueNumber] = 0;
        }
        foreach ($months as $month) {
            $count = count($month['uniqueIssuesClosed']);
            $months[$month[0]]['uniqueIssuesClosed'] = $count;
        }
        Helpers::writeToCsv(['month', 'closed issue count'], $months, 'issues-closed-by-month.csv');
    }

    public function newCrediteesByMonth()
    {
        $months = $this->findNewCrediteesByMonth();
        foreach ($months as $month => $values) {
            unset($months[$month]['ages']);
            $chartArray['labels'][] = $month;
            $chartArray['newCreditees'][] = $values['count'];
            $chartArray['averageAgeInMonths'][] = $values['averageAge'] / (31557600 / 12);
        }
        Helpers::exportToJson($chartArray, 'creditees-by-month.json');
        Helpers::writeToCsv(['month', 'new creditees', 'average account age'], $months, 'creditees-by-month.csv');
    }

    private function findNewCrediteesByMonth()
    {
        $months = $this->initializeMonthsWithCustomKeys([
            'count' => 0,
            'ages' => [],
            'averageAge' => 0,
        ]);
        foreach ($this->creditees as $creditee) {
            $firstCommitDate = new \DateTime();
            $firstCommitDate->setTimestamp($creditee->firstCommitTimestamp);
            $firstCommitYearMonth = $firstCommitDate->format('Y-m');
            $age = ($creditee->firstCommitTimestamp - $creditee->accountCreatedTimestamp);
            if ($age < 0) {
                // @todo _something_
            }
            $months[$firstCommitYearMonth]['count']++;
            $months[$firstCommitYearMonth]['ages'][] = $age;
            $months[$firstCommitYearMonth]['averageAge'] = (array_sum($months[$firstCommitYearMonth]['ages'])/count($months[$firstCommitYearMonth]['ages']));
        }

        return $months;
    }

    public function topContributors(int $after = null, int $before = null)
    {
        $contributors = [];
        if (!$after) {
            $after = strtotime('2021-11-30'); // Date D10 was opened.
        }
        if (!$before) {
            $before = time();
        }
        foreach ($this->commits as $commit) {
            $commitTimestamp = strtotime($commit->date->date);
            if ($commitTimestamp > $before) {
                continue;
            }
            if ($commitTimestamp < $after) {
                continue;
            }
            foreach ($commit->creditees as $creditee) {
                $contributors[$creditee][$commit->issueNumber][] = $commit->hash;
            }
        }
        arsort($contributors);
        $topContributors = array_slice($contributors, 0, 100);
        foreach ($topContributors as $name => $contributorCommits) {
            $processedTopContributors[$name] = [$name, count($contributorCommits)];
        }
        Helpers::writeToCsv(['name', 'credits'], $processedTopContributors, 'topD10-drupalOnly.csv');
    }

    private function initializeMonthsWithCustomKeys(array $additionalKeysValues = [], int $startTimestamp = 946702800): array
    {
        $months = [];
        $date = new \DateTime();
        $date->setTimestamp($startTimestamp);
        while ($date->getTimestamp() < time()) {
            $month = $date->format('Y-m');
            foreach ($additionalKeysValues as $key => $defaultValue) {
                $months[$month][$key] = $defaultValue;
            }
            $date->add(\DateInterval::createFromDateString('1 month'));
        }
        $months = Helpers::includeArrayKeysInArray($months);
        return $months;
    }
}