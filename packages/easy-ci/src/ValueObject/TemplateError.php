<?php

declare(strict_types=1);

namespace Symplify\EasyCI\ValueObject;

use Symplify\EasyCI\Contract\ValueObject\TemplateErrorInterface;
use Symplify\SmartFileSystem\SmartFileInfo;

final class TemplateError implements TemplateErrorInterface
{
    public function __construct(
        private string $errorMessage,
        private SmartFileInfo $smartFileInfo
    ) {
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getRelativeFilePath(): string
    {
        return $this->smartFileInfo->getRelativeFilePath();
    }
}