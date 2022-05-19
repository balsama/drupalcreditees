<?php

namespace Balsama\Dealth\Tests;

use Balsama\Dealth\Commits;
use PHPUnit\Framework\TestCase;

class CommitsTest extends TestCase
{

    private Commits $commits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commits = new Commits();
    }

    public function testGatherCommits()
    {
        $commits = $this->commits->gatherCommits();

        $this->assertIsArray($commits);
        $this->assertGreaterThan(10000, count($commits));
        $this->assertInstanceOf('Gitonomy\Git\Commit', reset($commits));
    }

    public function testProcessCommits()
    {
        $commits = array_slice($this->commits->gatherCommits(), 0, 100);
        $this->commits->setCommits($commits);
        $processedCommits = $this->commits->processCommits();

        $this->assertIsArray($processedCommits);
        $this->assertInstanceOf('stdClass', reset($processedCommits));
    }

    public function testExtractCrediteesFromCommitMessage()
    {
        $commits = $this->commits->gatherCommits();
        $allCreditees = [];
        foreach ($commits as $commit) {
            $creditees = $this->commits->extractCrediteesFromCommitMessage($commit->getMessage());
            $allCreditees = array_unique(array_merge($creditees, $allCreditees));
        }

        $this->assertIsArray($allCreditees);
    }

}