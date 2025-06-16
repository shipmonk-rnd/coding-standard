# ShipMonk Coding Standard

PHP Coding Standard used in ShipMonk

> [!NOTE]
> This coding standard is primarily intended for use by ShipMonk packages. For other applications or packages, consider using [slevomat/coding-standard](https://github.com/slevomat/coding-standard) directly instead.

## Installation

```bash
composer require --dev shipmonk/coding-standard
```

## Configuration

Create `phpcs.xml.dist` in your project root:

```xml
<?xml version="1.0"?>
<ruleset
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/squizlabs/php_codesniffer/phpcs.xsd"
>
    <arg name="basepath" value="."/>
    <arg name="cache" value="var/phpcs.cache"/>

    <file>src/</file>
    <file>tests/</file>

    <config name="installed_paths" value="vendor/slevomat/coding-standard,vendor/shipmonk/coding-standard"/>
    <rule ref="ShipMonkCodingStandard"/>
</ruleset>
```

## Usage

Check your code:
```bash
vendor/bin/phpcs
```

Fix auto-fixable issues:
```bash
vendor/bin/phpcbf
```
