<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PackageBuilder\Matcher\ArrayStringAndFnMatcher;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\NoClassWithStaticMethodWithoutStaticNameRule\NoClassWithStaticMethodWithoutStaticNameRuleTest
 */
final class NoClassWithStaticMethodWithoutStaticNameRule implements Rule, DocumentedRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Class has a static method must so must contains "Static" in its name';

    /**
     * @var array<class-string>
     */
    private const ALLOWED_CLASS_TYPES = [
        // symfony classes with static methods
        'Symfony\Component\EventDispatcher\EventSubscriberInterface',
        'Symfony\Component\Console\Command\Command',
    ];

    public function __construct(
        private NodeFinder $nodeFinder,
        private SimpleNameResolver $simpleNameResolver,
        private ArrayStringAndFnMatcher $arrayStringAndFnMatcher
    ) {
    }

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->isClassWithStaticMethod($node)) {
            return [];
        }

        // skip anonymous class
        $shortClassName = (string) $node->name;
        if ($shortClassName === '') {
            return [];
        }

        // already has "Static" in the name
        if (\str_contains($shortClassName, 'Static')) {
            return [];
        }

        if ($this->shouldSkipClassName($node)) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public static function getSome()
    {
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeStaticClass
{
    public static function getSome()
    {
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    private function isClassWithStaticMethod(Class_ $class): bool
    {
        $classMethods = $class->getMethods();

        foreach ($classMethods as $classMethod) {
            if (! $classMethod->isStatic()) {
                continue;
            }

            if ($this->isStaticConstructorOfValueObject($classMethod)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function shouldSkipClassName(Class_ $class): bool
    {
        $className = $this->simpleNameResolver->getName($class);
        if ($className === null) {
            return true;
        }

        return $this->arrayStringAndFnMatcher->isMatchWithIsA($className, self::ALLOWED_CLASS_TYPES);
    }

    private function isStaticConstructorOfValueObject(ClassMethod $classMethod): bool
    {
        return (bool) $this->nodeFinder->findFirst((array) $classMethod->stmts, function (Node $node): bool {
            if (! $node instanceof Return_) {
                return false;
            }

            $returnedExpr = $node->expr;
            if (! $returnedExpr instanceof New_) {
                return false;
            }

            return $this->simpleNameResolver->isName($returnedExpr->class, 'self');
        });
    }
}
