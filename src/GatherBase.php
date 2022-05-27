<?php

namespace Balsama\Dealth;

use Symfony\Component\Yaml\Yaml;

class GatherBase
{

    public function __construct()
    {
        $yaml = new Yaml();
        $this->config = $yaml::parseFile(__DIR__ . '/../config/config.yml');
    }

    protected static function getStandardNameRemaps()
    {
        $yaml = new Yaml();
        return $yaml::parse(file_get_contents('https://raw.githubusercontent.com/lauriii/drupalcores/master/app/config/name_mappings.yml'));
    }

}