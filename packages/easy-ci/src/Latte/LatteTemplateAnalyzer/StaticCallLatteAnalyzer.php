<?php

declare(strict_types=1);

namespace Symplify\EasyCI\Latte\LatteTemplateAnalyzer;

use Nette\Utils\Strings;
use Symplify\EasyCI\Contract\ValueObject\FileErrorInterface;
use Symplify\EasyCI\Latte\Contract\LatteTemplateAnalyzerInterface;
use Symplify\EasyCI\ValueObject\FileError;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Symplify\EasyCI\Tests\Latte\LatteTemplateAnalyzer\LatteStaticCallAnalyzer\StaticCallLatteAnalyzerTest
 */
final class StaticCallLatteAnalyzer implements LatteTemplateAnalyzerInterface
{
    /**
     * @var string
     */
    private const CLASS_NAME_PART = 'class';

    /**
     * @var string
     */
    private const METHOD_NAME_PART = 'method';

    /**
     * @var string
     * @see https://regex101.com/r/mDzFKI/4
     */
    private const STATIC_CALL_REGEX = '#(?<' .
        self::CLASS_NAME_PART . '>(\$|\b[A-Z])[\w\\\\]+)::(?<' .
        self::METHOD_NAME_PART . '>[\w]+)\((.*?)?\)#m';

    /**
     * @param SmartFileInfo[] $fileInfos
     * @return FileErrorInterface[]
     */
    public function analyze(array $fileInfos): array
    {
        $templateErrors = [];
        foreach ($fileInfos as $fileInfo) {
            $currentTemplateErrors = $this->analyzeFileInfo($fileInfo);
            $templateErrors = array_merge($templateErrors, $currentTemplateErrors);
        }

        return $templateErrors;
    }

    /**
     * @return FileErrorInterface[]
     */
    private function analyzeFileInfo(SmartFileInfo $fileInfo): array
    {
        $matches = Strings::matchAll($fileInfo->getContents(), self::STATIC_CALL_REGEX);
        $matches = $this->filterOutAllowedStaticClasses($matches);

        $templateErrors = [];
        foreach ($matches as $match) {
            $errorMessage = sprintf(
                'Static call "%s::%s()" should not be used in template, move to filter provider instead',
                $match[self::CLASS_NAME_PART],
                $match[self::METHOD_NAME_PART]
            );

            $templateErrors[] = new FileError($errorMessage, $fileInfo);
        }

        return $templateErrors;
    }

    /**
     * @param string[][] $matches
     * @return string[][]
     */
    private function filterOutAllowedStaticClasses(array $matches): array
    {
        return array_filter(
            $matches,
            fn (array $match): bool => ! in_array($match[self::CLASS_NAME_PART], [
                // keep strings, to avoid prefixing
                'Nette\Utils\Strings',
                'Nette\Utils\DateTime',
            ], true)
        );
    }
}
