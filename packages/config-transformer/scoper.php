<?php

declare(strict_types=1);

use Nette\Utils\Strings;

require __DIR__ . '/vendor/autoload.php';

/**
 * @see https://regex101.com/r/LMDq0p/1
 * @var string
 */
const POLYFILL_FILE_NAME_REGEX = '#vendor\/symfony\/polyfill\-(.*)\/bootstrap(.*?)\.php#';

/**
 * @see https://regex101.com/r/RBZ0bN/1
 * @var string
 */
const POLYFILL_STUBS_NAME_REGEX = '#vendor\/symfony\/polyfill\-(.*)\/Resources\/stubs#';

$timestamp = (new DateTime('now'))->format('Ymd');

// see https://github.com/humbug/php-scoper
return [
    'prefix' => 'ConfigTransformer' . $timestamp . random_int(0, 10),
    'whitelist' => [
        // part of public interface of configs.php
        'Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator',
    ],
    'files-whitelist' => [
        // do not prefix "trigger_deprecation" from symfony - https://github.com/symfony/symfony/commit/0032b2a2893d3be592d4312b7b098fb9d71aca03
        // these paths are relative to this file location, so it should be in the root directory
        'vendor/symfony/deprecation-contracts/function.php',
        // for package versions - https://github.com/symplify/easy-coding-standard-prefixed/runs/2176047833
    ],
    'patchers' => [
        // unprefix strings used for config printing
        // fixes https://github.com/symplify/symplify/issues/3976
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::match($filePath, '#Symplify\/PhpConfigPrinter\/ValueObject\/FunctionName\.php#')) {
                return $content;
            }

            $pattern = sprintf('#public const (.*?) = \'%s\\\\\\\\#', $prefix);

            return Strings::replace($content, $pattern, 'public const $1 = \'');
        },
        // unprefix polyfill functions
        // @see https://github.com/humbug/php-scoper/issues/440#issuecomment-795160132
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::match($filePath, POLYFILL_FILE_NAME_REGEX)) {
                return $content;
            }

            return Strings::replace($content, '#namespace ' . $prefix . ';#', '');
        },
        // remove namespace frompoly fill stubs
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::match($filePath, POLYFILL_STUBS_NAME_REGEX)) {
                return $content;
            }

            // remove alias to class have origina PHP names - fix in
            $content = Strings::replace($content, '#\\\\class_alias(.*?);#', '');

            return Strings::replace($content, '#namespace ' . $prefix . ';#', '');
        },

        // scope symfony configs
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::match($filePath, '#(packages|config|services)\.php$#')) {
                return $content;
            }

            // fix symfony config load scoping, except CodingStandard and EasyCodingStandard
            $content = Strings::replace(
                $content,
                '#load\(\'Symplify\\\\\\\\(?<package_name>[A-Za-z]+)#',
                function (array $match) use ($prefix) {
                    return 'load(\'' . $prefix . '\Symplify\\' . $match['package_name'];
                }
            );

            return $content;
        },

        // fixes https://github.com/symplify/symplify/issues/3102
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::contains($filePath, 'vendor/')) {
                return $content;
            }

            // @see https://regex101.com/r/lBV8IO/2
            $fqcnReservedPattern = sprintf('#(\\\\)?%s\\\\(parent|self|static)#m', $prefix);
            $matches = Strings::matchAll($content, $fqcnReservedPattern);

            if (! $matches) {
                return $content;
            }

            foreach ($matches as $match) {
                $content = str_replace($match[0], $match[2], $content);
            }

            return $content;
        },

        // unprefixed ContainerConfigurator
        function (string $filePath, string $prefix, string $content): string {
            // keep vendor prefixed the prefixed file loading; not part of public API
            // except @see https://github.com/symfony/symfony/commit/460b46f7302ec7319b8334a43809523363bfef39#diff-1cd56b329433fc34d950d6eeab9600752aa84a76cbe0693d3fab57fed0f547d3R110
            if (str_contains($filePath, 'vendor/symfony') && ! str_ends_with(
                $filePath,
                'vendor/symfony/dependency-injection/Loader/PhpFileLoader.php'
            )) {
                return $content;
            }

            return Strings::replace(
                $content,
                '#' . $prefix . '\\\\Symfony\\\\Component\\\\DependencyInjection\\\\Loader\\\\Configurator\\\\ContainerConfigurator#',
                'Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator'
            );
        },
    ],
];
