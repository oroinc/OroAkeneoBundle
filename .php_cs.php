<?php

$finder = \PhpCsFixer\Finder::create()
    ->in(__DIR__);

return (new \PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules(
        [
            // generic PSRs
            '@PSR1' => true,
            '@PSR2' => true,
            '@PSR12' => true,
            '@PSR12:risky' => true,
            'psr_autoloading' => true,

            // imports
            'ordered_imports' => true,
            'no_extra_blank_lines' => ['use'],

            // PhpCsFixer, but exclude Oro cases
            '@PhpCsFixer' => true,
            '@PhpCsFixer:risky' => true,
            'multiline_whitespace_before_semicolons' => false,
            'method_chaining_indentation' => false,
            'php_unit_internal_class' => false,
            'php_unit_test_class_requires_covers' => false,
            'final_internal_class' => false,
            'php_unit_strict' => false,
            'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
            'ordered_class_elements' => false,
            'array_indentation' => false,
            'phpdoc_order' => false,
            'strict_comparison' => false,
            'explicit_string_variable' => false,
            'php_unit_set_up_tear_down_visibility' => false,
            'strict_param' => false,
            'heredoc_to_nowdoc' => false,
            'return_assignment' => false,
            'escape_implicit_backslashes' => false,

            // PHP
            '@PHP73Migration' => true,
            '@PHP74Migration' => true,
            '@PHP74Migration:risky' => true,

            'use_arrow_functions' => false,
            'get_class_to_class_keyword' => false,
            'void_return' => false,
            'list_syntax' => ['syntax' => 'long'],
            'declare_strict_types' => false,

            // Doctrine with Oro compat
            '@DoctrineAnnotation' => true,
            'doctrine_annotation_array_assignment' => ['operator' => '='],
            'doctrine_annotation_braces' => false,
            'doctrine_annotation_indentation' => false,
            'doctrine_annotation_spaces' => [
                'after_array_assignments_colon' => false,
                'after_array_assignments_equals' => false,
                'before_array_assignments_colon' => false,
                'before_array_assignments_equals' => false,
            ],

            // Symfony, but exclude Oro cases
            '@Symfony' => true,
            '@Symfony:risky' => true,
            'phpdoc_types_order' => false,
            'phpdoc_separation' => false,
            'visibility_required' => ['elements' => ['property', 'method']],
            'types_spaces' => false,
            'native_function_invocation' => false,
            'concat_space' => ['spacing' => 'one'],
            'single_space_after_construct' => false,
            'self_accessor' => false,
            'yoda_style' => false,
            'phpdoc_summary' => false,
            'binary_operator_spaces' => false,
            'phpdoc_align' => false,
            'cast_spaces' => false,
            'phpdoc_annotation_without_dot' => false,
            'standardize_increment' => false,
            'phpdoc_scalar' => false,
            'phpdoc_var_without_name' => false,
            'increment_style' => false,
            'non_printable_character' => false,
            'phpdoc_no_alias_tag' => false,
            'single_quote' => false,
            'fopen_flags' => false,
            'ternary_operator_spaces' => false,
            'phpdoc_no_useless_inheritdoc' => false,
            'class_definition' => false,
            'string_length_to_empty' => false,
        ]
    )
    ->setRiskyAllowed(true);
