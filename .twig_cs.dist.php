<?php

declare(strict_types=1);

use FriendsOfTwig\Twigcs;

$finder = Twigcs\Finder\TemplateFinder::create()->in(__DIR__.'/templates');

return Twigcs\Config\Config::create()
    ->setName('project_warehouse_app')
    ->setSeverity('warning')
    ->setRuleset(Twigcs\Ruleset\Official::class)
    ->setTemplateResolver(new Twigcs\TemplateResolver\FileResolver(__DIR__.'/templates'))
    ->setFinder($finder)
;