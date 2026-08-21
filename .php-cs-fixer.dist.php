<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('demo')
    ->exclude(['tests/Fixtures/app/var', 'tests/Fixtures/encore/var', 'tests/Fixtures/reprise/var'])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        'fully_qualified_strict_types' => ['import_symbols' => true, 'leading_backslash_in_global_namespace' => true],
        '@Symfony' => true,
        'list_syntax' => ['syntax' => 'short'],
        'array_syntax' => ['syntax' => 'short'],
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
        'no_unused_imports' => true,
        'declare_strict_types' => true,
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'only_if_meta',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'only_if_meta',
            ],
        ],
    ])
    ->setFinder($finder)
;
