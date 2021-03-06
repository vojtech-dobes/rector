<?php declare(strict_types=1);

namespace Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitorAbstract;
use Rector\Application\AppliedRectorCollector;
use Rector\Contract\Rector\PhpRectorInterface;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\Attribute;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;
use function Safe\sprintf;

abstract class AbstractRector extends NodeVisitorAbstract implements PhpRectorInterface
{
    use TypeAnalyzerTrait;
    use NameResolverTrait;
    use ConstFetchAnalyzerTrait;
    use BetterStandardPrinterTrait;
    use NodeCommandersTrait;
    use NodeFactoryTrait;

    /**
     * @var AppliedRectorCollector
     */
    private $appliedRectorCollector;

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    /**
     * @required
     */
    public function setAbstractRectorDependencies(
        AppliedRectorCollector $appliedRectorCollector,
        SymfonyStyle $symfonyStyle
    ): void {
        $this->appliedRectorCollector = $appliedRectorCollector;
        $this->symfonyStyle = $symfonyStyle;
    }

    /**
     * @return int|Node|null
     */
    final public function enterNode(Node $node)
    {
        if (! $this->isMatchingNodeType(get_class($node))) {
            return null;
        }

        // show current Rector class on --debug
        if ($this->symfonyStyle->isDebug()) {
            $this->symfonyStyle->writeln(static::class);
        }

        $originalNode = $node;
        $node = $this->refactor($node);
        if ($node === null) {
            return null;
        }

        // changed!
        if ($originalNode !== $node) {
            // transfer attributes that are needed further
            if ($node->getAttribute(Attribute::FILE_INFO) === null) {
                $node->setAttribute(Attribute::FILE_INFO, $originalNode->getAttribute(Attribute::FILE_INFO));
            }

            $this->notifyNodeChangeFileInfo($node);
        }

        if ($originalNode instanceof Stmt && $node instanceof Expr) {
            return new Expression($node);
        }

        return $node;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function afterTraverse(array $nodes): array
    {
        // @todo foreach, array autowire on Required in CommanderInterface[]
        if ($this->nodeAddingCommander->isActive()) {
            $nodes = $this->nodeAddingCommander->traverseNodes($nodes);
        }

        if ($this->propertyAddingCommander->isActive()) {
            $nodes = $this->propertyAddingCommander->traverseNodes($nodes);
        }

        if ($this->nodeRemovingCommander->isActive()) {
            $nodes = $this->nodeRemovingCommander->traverseNodes($nodes);
        }

        return $nodes;
    }

    protected function notifyNodeChangeFileInfo(Node $node): void
    {
        /** @var SmartFileInfo|null $fileInfo */
        $fileInfo = $node->getAttribute(Attribute::FILE_INFO);
        if ($fileInfo === null) {
            throw new ShouldNotHappenException(sprintf(
                'Node is missing "%s" attribute.%sYou probably created a new node and forgot to move attributes of old one in "%s".',
                Attribute::FILE_INFO,
                PHP_EOL,
                static::class
            ));
        }

        $this->appliedRectorCollector->addRectorClass(static::class, $fileInfo);
    }

    private function isMatchingNodeType(string $nodeClass): bool
    {
        foreach ($this->getNodeTypes() as $nodeType) {
            if (is_a($nodeClass, $nodeType, true)) {
                return true;
            }
        }

        return false;
    }
}
