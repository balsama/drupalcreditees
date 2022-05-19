<?php

namespace Balsama\Dealth\Tests;

use Balsama\Dealth\Creditees;
use PHPUnit\Framework\TestCase;

class CrediteesTest extends TestCase
{

    private Creditees $creditees;
    private $processedCommits;

    public function setUp(): void
    {
        parent::setUp();
        $this->processedCommits = (array) json_decode(file_get_contents(__DIR__ . '/../data/commits.json'));
        $this->creditees = new Creditees($this->processedCommits);
    }

    public function testFindUniqueCreditees()
    {
        $uniqueCreditees = $this->creditees->findUniqueCreditees($this->processedCommits);
        $this->assertIsArray($uniqueCreditees);
        $this->assertGreaterThan(10000, count($uniqueCreditees));
    }

    public function testValidateCreditees()
    {
        $uniqueCreditees = [
            'nod_',
            'hooroomoo',
            'notavalidusername'
        ];
        $validatedCreditees = $this->creditees->validateCreditees($uniqueCreditees);
        $this->assertIsArray($validatedCreditees);
    }

    public function testProcessValidCreditees()
    {
        $validatedCreditees = (array) json_decode(file_get_contents(__DIR__ . '/test_data/sample--validatedCreditees.json'));
        $processedCreditees = $this->creditees->processValidCreditees($validatedCreditees);
        $aProcessedCreditee = reset($processedCreditees);

        $this->assertIsArray($processedCreditees);
        $this->assertInstanceOf('stdClass', $aProcessedCreditee);
        $this->assertTrue($aProcessedCreditee->firstCommitTimestamp < $aProcessedCreditee->mostRecentCommitTimestamp);
    }

}