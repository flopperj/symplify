<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\CheckConstantExpressionDefinedInConstructOrSetupRule;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Symplify\PHPStanRules\Rules\CheckConstantExpressionDefinedInConstructOrSetupRule;

/**
 * @extends RuleTestCase<CheckConstantExpressionDefinedInConstructOrSetupRule>
 */
final class CheckConstantExpressionDefinedInConstructOrSetupRuleTest extends RuleTestCase
{
    /**
     * @dataProvider provideData()
     * @param mixed[] $expectedErrorMessagesWithLines
     */
    public function testRule(string $filePath, array $expectedErrorMessagesWithLines): void
    {
        $this->analyse([$filePath], $expectedErrorMessagesWithLines);
    }

    public function provideData(): Iterator
    {
        yield [__DIR__ . '/Fixture/SkipPgExecWithInlinedVariable.php', []];
        yield [__DIR__ . '/Fixture/SkipOr.php', []];
        yield [__DIR__ . '/Fixture/SkipAssignOfVariable.php', []];
        yield [__DIR__ . '/Fixture/SkipConstFetchNonAssign.php', []];
        yield [__DIR__ . '/Fixture/SkipConcatOnClassConstFetch.php', []];
        yield [__DIR__ . '/Fixture/SkipInForeachAssign.php', []];
        yield [__DIR__ . '/Fixture/SkipInConstructOrSetUpMethod.php', []];
        yield [__DIR__ . '/Fixture/SkipPropertySetter.php', []];
        yield [__DIR__ . '/Fixture/SkipDataProvider.php', []];

        yield [__DIR__ . '/Fixture/SkipInsideOtherMethodInsideIf.php', []];
        yield [__DIR__ . '/Fixture/SkipAllowInsideOtherMethodUsedAfterDefinition.php', []];
        yield [__DIR__ . '/Fixture/SkipAllowMagicConstantWithConcatMethodCall.php', []];

        yield [__DIR__ . '/Fixture/FuncCallInConcat.php', [
            [CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 11],
        ]];

        yield [__DIR__ . '/Fixture/GetCwdFuncCallInConcat.php', [
            [CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 11],
        ]];

        yield [
            __DIR__ . '/Fixture/StringIntConcat.php',
            [
                [CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 11],
                [CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 13],
            ],
        ];

        yield [
            __DIR__ . '/Fixture/DisallowMagicConstantWithConcatString.php',
            [[CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 11]],
        ];

        yield [
            __DIR__ . '/Fixture/DisallowInsideOtherMethodNextDeadCode.php',
            [[CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 11]],
        ];

        yield [
            __DIR__ . '/Fixture/Multiplex.php',
            [
                [CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 11],
                [CheckConstantExpressionDefinedInConstructOrSetupRule::ERROR_MESSAGE, 13],
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/configured_rule.neon'];
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(CheckConstantExpressionDefinedInConstructOrSetupRule::class);
    }
}
