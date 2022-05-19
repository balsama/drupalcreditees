<?php

namespace Balsama\Dealth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;

use Symfony\Component\Console\Helper\ProgressBar;

use function PHPUnit\Framework\objectHasAttribute;

class Creditees extends GatherBase
{

    public array $processedCommits;
    public array $uniqueCreditees = [];
    private Client $client;
    private array $validatedCreditees = [];
    private array $invalidCreditees = [];

    public function __construct(array $processedCommits = null)
    {
        parent::__construct();
        if (!$processedCommits) {
            $processedCommits = json_decode(file_get_contents(__DIR__ . '/../data/commits.json'));
        }
        $this->processedCommits = $processedCommits;
        $this->client = new Client();
    }

    public function findUniqueCreditees(): array
    {
        $uniqueCreditees = [];
        foreach ($this->processedCommits as $processedCommit) {
            $uniqueCreditees = array_unique(array_merge($uniqueCreditees, $processedCommit->creditees));
        }
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
        foreach ($this->processedCommits as $commit) {
            $commitTimestamp = strtotime($commit->date->date);
            if ($crediteesToProcess = array_intersect($commit->creditees, array_keys($validatedCreditees))) {
                foreach ($crediteesToProcess as $crediteeToProcess) {
                    if (!array_key_exists($crediteeToProcess, $processedCreditees)) {
                        $newCreditee = new \stdClass();
                        $newCreditee->name = $crediteeToProcess;
                        $newCreditee->firstCommit = $commit;
                        $newCreditee->firstCommitTimestamp = $commitTimestamp;
                        $newCreditee->mostRecentCommit = $commit;
                        $newCreditee->mostRecentCommitTimestamp = $commitTimestamp;
                        $newCreditee->accountCreatedTimestamp = (int) $validatedCreditees[$crediteeToProcess]->created;
                        $newCreditee->uid = (int) $validatedCreditees[$crediteeToProcess]->uid;
                        $processedCreditees[$crediteeToProcess] = $newCreditee;
                    }
                    else {
                        if ($processedCreditees[$crediteeToProcess]->firstCommitTimestamp > $commitTimestamp) {
                            $processedCreditees[$crediteeToProcess]->firstCommit = $commit;
                            $processedCreditees[$crediteeToProcess]->firstCommitTimestamp = $commitTimestamp;
                        }
                        if ($processedCreditees[$crediteeToProcess]->mostRecentCommitTimestamp < $commitTimestamp) {
                            $processedCreditees[$crediteeToProcess]->mostRecentCommit = $commit;
                            $processedCreditees[$crediteeToProcess]->mostRecentCommitTimestamp = $commitTimestamp;
                        }
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
           throw new \Exception('Please provide a unique user agent in /config/config.yml');
       }
       return $this->config['useragent'];
    }

}
