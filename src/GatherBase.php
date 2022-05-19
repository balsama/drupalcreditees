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

}