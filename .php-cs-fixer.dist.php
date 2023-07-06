<?php

require_once __DIR__.'/fixers/PrettierPHPFixer.php';

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('public/sdk')
    ->exclude('bootstrap')
    ->exclude('storage')
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);
;

return (new PhpCsFixer\Config())
    ->registerCustomFixers([
        (new PrettierPHPFixer()),
    ])
    ->setRules([
        'Prettier/php' => true,
        '@PSR2'                 => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true
        ],
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'no_trailing_comma_in_list_call' => false,
    ])->setFinder($finder);
