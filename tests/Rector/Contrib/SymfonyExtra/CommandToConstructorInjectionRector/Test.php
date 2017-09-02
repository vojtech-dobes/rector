<?php declare(strict_types=1);

namespace Rector\Tests\Rector\Contrib\SymfonyExtra\CommandToConstructorInjectionRector;

use Rector\Rector\Contrib\SymfonyExtra\CommandToConstructorInjectionRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class Test extends AbstractRectorTestCase
{
    public function test(): void
    {
        $this->doTestFileMatchesExpectedContent(
            __DIR__ . '/Wrong/wrong.php.inc',
            __DIR__ . '/Correct/correct.php.inc'
        );
    }

    /**
     * @return string[]
     */
    protected function getRectorClasses(): array
    {
        return [CommandToConstructorInjectionRector::class];
    }
}
