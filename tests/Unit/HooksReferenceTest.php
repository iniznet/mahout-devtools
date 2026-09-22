<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Exception\InvalidHookDeclaration;
use Iniznet\Mahout\Devtools\Generator\Hooks\HooksReference;
use Iniznet\Mahout\Devtools\Generator\ReferenceGate;
use Iniznet\Mahout\Devtools\Generator\SourceFiles;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * The hook-reference generator must list only public hooks, must carry the
 * action-versus-filter and argument documentation, and must fail loudly when
 * the documentation is incomplete.
 *
 * @internal
 */
final class HooksReferenceTest extends TestCase
{
    public function testItRendersPublicHooksWithDocumentation(): void
    {
        $reference = $this->reference()->generate();

        self::assertStringContainsString('mahout/kernel/before_boot', $reference);
        self::assertStringContainsString('mahout/kernel/providers', $reference);
        self::assertStringContainsString('action', $reference);
        self::assertStringContainsString('filter', $reference);
        self::assertStringContainsString('object $kernel', $reference);
        self::assertStringContainsString('Fires before the kernel runs provider and module registration.', $reference);
    }

    public function testAPrivateConstantIsNotListed(): void
    {
        self::assertStringNotContainsString('mahout/kernel/internal', $this->reference()->generate());
    }

    public function testAPublicConstantOutsideAHooksClassIsNotListed(): void
    {
        self::assertStringNotContainsString('fixture/search_index', $this->reference()->generate());
    }

    public function testAPublicConstantOutsideAHooksClassNeedsNoHookTags(): void
    {
        $this->expectNotToPerformAssertions();

        $this->reference()->generate();
    }

    public function testGenerationIsDeterministic(): void
    {
        self::assertSame($this->reference()->generate(), $this->reference()->generate());
    }

    public function testTheCommittedReferenceIsCurrent(): void
    {
        $root = $this->root();
        $gate = new ReferenceGate($this->reference(), $root.'/fixtures/hooks/hooks.md');

        self::assertTrue($gate->isCurrent(), $gate->firstDifference());
    }

    public function testAMutatedClassDrifts(): void
    {
        $root = $this->root();
        $gate = new ReferenceGate($this->reference('fixtures/hooks/mutated'), $root.'/fixtures/hooks/hooks.md');

        self::assertFalse($gate->isCurrent());
    }

    public function testAnUndocumentedHookTypeIsRefused(): void
    {
        $this->expectException(InvalidHookDeclaration::class);

        $this->reference('fixtures/hooks/invalid')->generate();
    }

    private function reference(string $source = 'fixtures/hooks/source'): HooksReference
    {
        $root = $this->root();

        return new HooksReference(new SourceFiles([$root.'/'.$source], $root));
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
