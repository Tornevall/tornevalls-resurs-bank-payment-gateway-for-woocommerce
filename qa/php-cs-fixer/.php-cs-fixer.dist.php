<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->exclude(dirs: ['Gateway', 'ResursBank', 'Module', 'Service'])
    ->in(dirs: __DIR__ . '/../../src');

return (new PhpCsFixer\Config(name: 'Resursbank'))
    ->setRules(rules: ['@PSR12' => true])
    ->setFinder(finder: $finder);