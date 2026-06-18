<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// squizlabs/php_codesniffer ships no autoload metadata, so its PHP_CodeSniffer\* classes
// cannot be mapped back to the package by the analyser even though the sniffs use them.
return $config
    ->ignoreUnknownClassesRegex('~^PHP_CodeSniffer\\\\~')
    ->ignoreErrorsOnPackage('squizlabs/php_codesniffer', [ErrorType::UNUSED_DEPENDENCY]);
