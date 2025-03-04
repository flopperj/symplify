<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\ArrayType;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\Astral\NodeFinder\SimpleNodeFinder;
use Symplify\PackageBuilder\ValueObject\MethodName;
use Symplify\PHPStanRules\Naming\AssignToVariableChecker;
use Symplify\PHPStanRules\NodeAnalyzer\ArrayAnalyzer;
use Symplify\PHPStanRules\ParentGuard\ParentElementResolver\ParentMethodReturnTypeResolver;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ForbiddenArrayWithStringKeysRule\ForbiddenArrayWithStringKeysRuleTest
 */
final class ForbiddenArrayWithStringKeysRule implements Rule, DocumentedRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Array with keys is not allowed. Use value object to pass data instead';

    /**
     * @var string
     * @see https://regex101.com/r/ddj4mB/2
     */
    private const TEST_FILE_REGEX = '#(Test|TestCase)\.php$#';

    /**
     * @see https://regex101.com/r/TOKYyM/1
     * @var string
     */
    private const ARRAY_CONFIGURATION_NAMES_REGEX = '#(yaml|json|neon)#i';

    public function __construct(
        private ParentMethodReturnTypeResolver $parentMethodReturnTypeResolver,
        private SimpleNameResolver $simpleNameResolver,
        private SimpleNodeFinder $simpleNodeFinder,
        private ArrayAnalyzer $arrayAnalyzer,
        private AssignToVariableChecker $assignToVariableChecker
    ) {
    }

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Array_::class;
    }

    /**
     * @param Array_ $node
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->shouldSkipClass($scope, $node)) {
            return [];
        }

        if ($this->shouldSkipArray($node, $scope)) {
            return [];
        }

        if (! $this->arrayAnalyzer->isArrayWithStringKey($node)) {
            return [];
        }

        // is return array required by parent
        $parentMethodReturnType = $this->parentMethodReturnTypeResolver->resolve($scope);
        if ($parentMethodReturnType instanceof ArrayType) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        return [
            'name' => 'John',
            'surname' => 'Dope',
        ];
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        return new Person('John', 'Dope');
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    private function shouldSkipArray(Array_ $array, Scope $scope): bool
    {
        // skip part of attribute
        $parentAttribute = $this->simpleNodeFinder->findFirstParentByType($array, Attribute::class);
        if ($parentAttribute instanceof Attribute) {
            return true;
        }

        if (Strings::match($scope->getFile(), self::TEST_FILE_REGEX)) {
            return true;
        }

        // skip examples in Rector::getDefinition() method
        if (in_array($scope->getFunctionName(), ['getDefinition', MethodName::CONSTRUCTOR], true)) {
            return true;
        }

        if ($this->assignToVariableChecker->isAssignToVariableRegex($array, self::ARRAY_CONFIGURATION_NAMES_REGEX)) {
            return true;
        }

        return $this->isPartOfClassConstOrNew($array);
    }

    private function isPartOfClassConstOrNew(Array_ $array): bool
    {
        return (bool) $this->simpleNodeFinder->findFirstParentByTypes($array, [
            ClassConst::class,
            New_::class,
            MethodCall::class,
            StaticCall::class,
            FuncCall::class,
        ]);
    }

    private function shouldSkipClass(Scope $scope, Array_ $array): bool
    {
        $filePath = $scope->getFile();

        // php-scoper config, it return magic array by design
        if (\str_contains($filePath, 'scoper')) {
            return true;
        }

        // skip Symfony bundles.php
        if (\str_ends_with($filePath, 'bundles.php')) {
            return true;
        }

        $classMethod = $this->simpleNodeFinder->findFirstParentByType($array, ClassMethod::class);
        if (! $classMethod instanceof ClassMethod) {
            return false;
        }

        /** @var string $classMethodName */
        $classMethodName = $this->simpleNameResolver->getName($classMethod);

        $match = Strings::match($classMethodName, self::ARRAY_CONFIGURATION_NAMES_REGEX);
        return $match !== null;
    }
}
