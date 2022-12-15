<?php

namespace Balsama\Dealth;

use Gitonomy\Git\Commit;
use Symfony\Component\Filesystem\Filesystem;
use Gitonomy\Git\Repository;
use Gitonomy\Git\Admin;

class Commits extends GatherBase
{

    private Filesystem $fs;
    public array $repos_to_scan = [];
    /* @var \stdClass[] $processedCommits */
    private array $processedCommits;
    /* @var Repository[] */
    private array $repos = [];
    /* @var Commit[] */
    private array $commits = [];

    public function __construct()
    {
        parent::__construct();
        $this->repos_to_scan = $this->config['repos'];
        $this->setupTools();
    }

    public function gatherCommits()
    {
        $this->cloneAndUpdateRepos();
        return $this->commits = $this->gatherCommitsFromRepos();
    }

    public function processCommits()
    {
        $rawCommits = $this->commits;
        $processedCommits = [];
        foreach ($rawCommits as $commit) {
            $hash = $commit->getHash();
            $newCommit = new \stdClass();
            $newCommit->date = $commit->getCommitterDate();
            $newCommit->message = $commit->getMessage();
            $newCommit->creditees = $this->extractCrediteesFromCommitMessage($commit->getMessage());
            $newCommit->issueNumber = $this->extractIssueNumberFromCommitMessage($commit->getMessage());
            $newCommit->hash = $hash;
            $processedCommits[$hash] = $newCommit;
        }

        return $this->processedCommits = $processedCommits;
    }

    public function outputCommitsToFile($filename = 'commits.json')
    {
        Helpers::exportToJson($this->processedCommits, $filename);
    }

    /**
     * Clones local copies of the repos and checks out the defined branch.
     */
    public function cloneAndUpdateRepos()
    {
        $dir = __DIR__ . '/../repos/';
        $this->fs->mkdir($dir);
        foreach ($this->repos_to_scan as $name => $info) {
            if (!$this->fs->exists($dir . $name)) {
                Admin::cloneTo($dir . $name, $info['url'], false);
            }
            $this->repos[$name] = new Repository($dir . $name);
            $this->repos[$name]->run('fetch');
            $this->repos[$name]->run('checkout', [$info['branch']]);
            $this->repos[$name]->run('pull');
        }
    }

    public function gatherCommitsFromRepos()
    {
        $allCommits = [];
        foreach ($this->repos as $repo) {
            $log = $repo->getLog();
            $commits = $log->getCommits();
            $allCommits = array_merge($allCommits, $commits);
        }
        $this->commits = $allCommits;
        return $allCommits;
    }

    public function extractIssueNumberFromCommitMessage($commitMessage)
    {
        preg_match('/Issue #[0-9]{3,8}/', $commitMessage, $matches);
        if (!$matches) {
            return 0;
        }
        $parts = explode('#', reset($matches));
        return (int) $parts[1];
    }

    public function extractCrediteesFromCommitMessage($commitMessage)
    {
        // @todo This method is far from perfect. But so are the commit messages. Especially the older ones. It's messy
        // and finds a lot of things that aren't actually creditees. Creditees::validateCreditees() accounts foe this
        // and filters out false positives. Would be nice if it didn't have to and if this was more readable.
        preg_match('/by (.*?):/', $commitMessage, $names);
        if (count($names) < 2) {
            //
            if (str_contains($commitMessage, 'Back to dev')) {
                // Back to dev commits don't have creditees.
                return [];
            }
            if (preg_match(
                '/Drupal (?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?/',
                $commitMessage
            )) {
                // Tags don't have creditees
                return [];
            }
            if (str_contains($commitMessage, 'SA-CORE-')) {
                $names = explode(' by ', $commitMessage);
            }
            if (!$names) {
                return [];
            }
        }
        if (count($names) > 2) {
            throw new \Exception('Was there more than one match in this commit message: "' . $commitMessage . '"');
        }

        if (count($names) === 1) {
            return [];
        }

        $potentialNames = array_map('trim', explode(',', $names[1]));
        $newNames = [];
        foreach ($potentialNames as $potentialName) {
            $moreNames = array_map('trim', explode('/', $potentialName));
            if (count($moreNames) > 1) {
                $newNames = array_merge($moreNames, $newNames);
            }
            $moreNames = array_map('trim', explode(' and ', $potentialName));
            if (count($moreNames) > 1) {
                $newNames = array_merge($moreNames, $newNames);
            }
        }
        $potentialNames = array_merge($newNames, $potentialNames);

        $i = 0;
        foreach ($potentialNames as $potentialName) {
            if (str_contains($potentialName, '(cherry')) {
                $actualName = explode(PHP_EOL, $potentialName);
                $potentialNames[$i] = reset($actualName);
            }
            if (strlen($potentialName) > 64) {
                unset($potentialNames[$i]);
            }
            elseif (str_contains($potentialName, '?')) {
                unset($potentialNames[$i]);
            }
            elseif (str_contains($potentialName, '"')) {
                unset($potentialNames[$i]);
            }
            $i++;
        }

        return array_values($potentialNames);
    }

    public function getProcessedCommitLogs()
    {
        return $this->processedCommits;
    }

    public function setCommits(array $commits)
    {
        if (!(reset($commits) instanceof Commit)) {
            throw new \Exception('$commits must be an array of Gitonomy\Git\Commit\'s');
        }
        $this->commits = $commits;
    }

    protected function setupTools()
    {
        $this->fs = new Filesystem();
    }

}
