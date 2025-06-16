<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;


$config = new Configuration();

return $config
    ->ignoreErrorsOnPackage('slevomat/coding-standard', [ErrorType::UNUSED_DEPENDENCY]);
