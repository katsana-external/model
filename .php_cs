<?php

$finder = PhpCsFixer\Finder::create()
            ->in(__DIR__.'/src')
            ->in(__DIR__.'/tests');

return (new PhpCsFixer\Config())
            ->setRiskyAllowed(false)
            ->setRules([
                '@Symfony' => true,
                'array_syntax' => ['syntax' => 'short'],
                'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
                'no_extra_blank_lines' => false,
                'no_empty_comment' => false,
                'no_unneeded_control_parentheses' => false,
                'not_operator_with_successor_space' => true,
                'ordered_imports' => ['sort_algorithm' => 'alpha'],
                'phpdoc_align' => false,
                'phpdoc_no_empty_return' => false,
                'phpdoc_order' => true,
                'php_unit_method_casing' => false,
                'pre_increment' => false,
                'self_accessor' => false,
                'single_trait_insert_per_statement' => false,
                'yoda_style' => false,
            ])
            ->setFinder($finder);
