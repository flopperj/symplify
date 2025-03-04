<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\Explicit\NoGetterAndPropertyRule\Fixture;

final class SkipProtectedProperty
{
    protected $name;

    public function getName()
    {
        return $this->name;
    }
}
