<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

$finder = new Finder();
$finder->in(__DIR__ . '/templates');
$finder->exclude('vendor');

$ruleset = new Ruleset();
$ruleset->addStandard(new TwigCsFixer());

$config = new Config();
$config->setFinder($finder);
$config->setRuleset($ruleset);
$config->setCacheFile(__DIR__ . '/.twig-cs-fixer.cache');

return $config;
