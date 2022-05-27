<?php

namespace Balsama\Dealth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Helper\ProgressBar;

class Creditees extends GatherBase
{

    public array $processedCommits;
    public array $uniqueCreditees = [];
    private Client $client;
    private array $validatedCreditees = [];
    private array $outdatedNames = [];
    private array $invalidCreditees = [];

    public function __construct(array $processedCommits = null)
    {
        parent::__construct();
        if (!$processedCommits) {
            $processedCommits = json_decode(file_get_contents(__DIR__ . '/../data/commits.json'));
        }
        $this->processedCommits = $processedCommits;
        $this->outdatedNames = $this->usernamesRemaps();

        $this->client = new Client();
    }

    public function findUniqueCreditees(): array
    {
        $uniqueCreditees = [];
        foreach ($this->processedCommits as $processedCommit) {
            $uniqueCreditees = array_unique(array_merge($uniqueCreditees, $processedCommit->creditees));
        }
        $uniqueCreditees = array_unique(array_merge($uniqueCreditees, $this->outdatedNames));
        return $this->uniqueCreditees = $uniqueCreditees;
    }

    public function validateCreditees(array $uniqueCreditees, ProgressBar $progressBar = null): array
    {
        $progressBar?->start(count($uniqueCreditees));
        $validatedCreditees = [];
        foreach ($uniqueCreditees as $uniqueCreditee) {
            $validatedCreditee = $this->validateCreditee($uniqueCreditee);
            if ($validatedCreditee) {
                $validatedCreditees[$validatedCreditee->name] = $validatedCreditee;
            }
            else {
                $this->invalidCreditees[] = $uniqueCreditee;
            }
            $progressBar?->advance();
        }

        return $this->validatedCreditees = $validatedCreditees;
    }

    public function processValidCreditees(array $validatedCreditees = null): array
    {
        if (!$validatedCreditees) {
            $validatedCreditees = $this->validatedCreditees;
        }
        $processedCreditees = [];
        $knownInvalidUsernames = [];
        foreach ($this->processedCommits as $commit) {
            if ($crediteesToProcess = array_intersect($commit->creditees, array_keys($validatedCreditees))) {
                foreach ($crediteesToProcess as $crediteeToProcess) {
                    if (!array_key_exists($crediteeToProcess, $processedCreditees)) {
                        $processedCreditees[$crediteeToProcess] = $this->processNewCreditee($validatedCreditees[$crediteeToProcess], $commit);
                    }
                    else {
                        $processedCreditees[$crediteeToProcess] = $this->processExistingCreditee($processedCreditees[$crediteeToProcess], $commit);
                    }
                }
            }
            elseif ($crediteesToProcess = array_intersect($commit->creditees, array_keys($this->outdatedNames))) {
                foreach ($crediteesToProcess as $crediteeToProcess) {
                    $crediteeToProcess = $this->outdatedNames[$crediteeToProcess];
                    if (!array_key_exists($crediteeToProcess, $processedCreditees)) {
                        if (!array_key_exists($crediteeToProcess, $validatedCreditees)) {
                            if (in_array($crediteeToProcess, $knownInvalidUsernames)) {
                                continue;
                            }
                            elseif ($newValidatedCreditee = $this->validateCreditee($crediteeToProcess)) {
                                $validatedCreditees[$crediteeToProcess] = $newValidatedCreditee;
                            }
                            else {
                                $knownInvalidUsernames[] = $crediteeToProcess;
                                continue;
                            }
                        }
                        $processedCreditees[$crediteeToProcess] = $this->processNewCreditee($validatedCreditees[$crediteeToProcess], $commit);
                    }
                    else {
                        $processedCreditees[$crediteeToProcess] = $this->processExistingCreditee($processedCreditees[$crediteeToProcess], $commit);
                    }
                }
            }
        }

        return $processedCreditees;
    }

    public function validateCreditee($uniqueCreditee)
    {
        $uri = 'https://www.drupal.org/api-d7/user.json?name=' . $uniqueCreditee;
        $body = $this->fetch($uri);
        return reset($body->list);
    }

    private function processNewCreditee(\stdClass $crediteeToProcess, \stdClass $commit)
    {
        $newCreditee = new \stdClass();
        $newCreditee->name = $crediteeToProcess->name;
        $newCreditee->firstCommit = $commit;
        $newCreditee->firstCommitTimestamp = strtotime($commit->date->date);
        $newCreditee->mostRecentCommit = $commit;
        $newCreditee->mostRecentCommitTimestamp = strtotime($commit->date->date);
        $newCreditee->accountCreatedTimestamp = (int) $crediteeToProcess->created;
        $newCreditee->uid = (int) $crediteeToProcess->uid;
        return $newCreditee;
    }

    private function processExistingCreditee(\stdClass $crediteeToProcess, \stdClass $commit)
    {
        if ($crediteeToProcess->firstCommitTimestamp > strtotime($commit->date->date)) {
            $crediteeToProcess->firstCommit = $commit;
            $crediteeToProcess->firstCommitTimestamp = strtotime($commit->date->date);
        }
        if ($crediteeToProcess->mostRecentCommitTimestamp < strtotime($commit->date->date)) {
            $crediteeToProcess->mostRecentCommit = $commit;
            $crediteeToProcess->mostRecentCommitTimestamp = strtotime($commit->date->date);
        }
        return $crediteeToProcess;
    }

    private function usernamesRemaps(): array
    {
        $oldUsernames = $this::getStandardNameRemaps();
        $oldUsernames = array_unique(array_merge($this->config['additionalnamemappings'], $oldUsernames));
        return $oldUsernames;
    }

    private function fetch($url, $retryOnError = 5)
    {
        try {
            $options = ['headers' => ['User-Agent' => $this->getUserAgent()]];
            $response = $this->client->get($url, $options);
            return json_decode($response->getBody());
        } catch (ServerException $e) {
            if ($retryOnError) {
                $retryOnError--;
                usleep(250000);
                return self::fetch($retryOnError);
            }
            throw $e;
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    private function getUserAgent(): string
    {
       if ($this->config['useragent'] === '<YOUR_USERAGENT>') {
           throw new \Exception('Please provide a unique user agent in config/config.yml');
       }
       return $this->config['useragent'];
    }

}
