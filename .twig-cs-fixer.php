<?php

declare(strict_types=1);

$ruleset = new TwigCsFixer\Ruleset\Ruleset();
$ruleset->addStandard(new TwigCsFixer\Standard\Symfony());

$config = new TwigCsFixer\Config\Config();
$config->setRuleset($ruleset);

$finder = $config->getFinder();
$finder->in(__DIR__.'/src/Resources/views');

return $config;
