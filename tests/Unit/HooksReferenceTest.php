<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Exception\InvalidHookDeclaration;
use Iniznet\Mahout\Devtools\Generator\Hooks\HookDefinition;
use Iniznet\Mahout\Devtools\Generator\Hooks\HookDocument;
use Iniznet\Mahout\Devtools\Generator\Hooks\HooksReference;
use Iniznet\Mahout\Devtools\Generator\Hooks\HookType;
use Iniznet\Mahout\Devtools\Generator\ReferenceGate;
use Iniznet\Mahout\Devtools\Generator\SourceFiles;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * The hook reference is two documents, and the generator must keep them apart: an action
 * never appears in the filter document and a filter never in the action document, while the
 * documentation rules that made a single reference trustworthy still apply to both.
 *
 * @internal
 */
final class HooksReferenceTest extends TestCase
{
    public function testActionsAreListedInTheDocumentOfTheirOwn(): void
    {
        $actions = $this->document(HookType::Action);

        self::assertStringContainsString('mahout/kernel/before_boot', $actions);
        self::assertStringContainsString('object $kernel', $actions);
        self::assertStringContainsString('Fires before the kernel runs provider and module registration.', $actions);
        self::assertStringNotContainsString('mahout/kernel/providers', $actions);
        self::assertStringNotContainsString('mahout/kernel/modules', $actions);
    }

    public function testFiltersAreListedInTheDocumentOfTheirOwn(): void
    {
        $filters = $this->document(HookType::Filter);

        self::assertStringContainsString('mahout/kernel/providers', $filters);
        self::assertStringContainsString('mahout/kernel/modules', $filters);
        self::assertStringContainsString('list<class-string> $providers', $filters);
        self::assertStringNotContainsString('mahout/kernel/before_boot', $filters);
    }

    public function testNeitherDocumentCarriesTheOtherKindsColumn(): void
    {
        // The kind is a heading and a file, not a column: a document that still printed the
        // word would let a reader believe the other kind had been merged in.
        self::assertStringNotContainsString('| action |', $this->document(HookType::Action));
        self::assertStringNotContainsString('| filter |', $this->document(HookType::Filter));
    }

    public function testEachDocumentPointsAtTheOther(): void
    {
        self::assertStringContainsString('`filters.md`', $this->document(HookType::Action));
        self::assertStringContainsString('`actions.md`', $this->document(HookType::Filter));
    }

    public function testAPrivateConstantIsNotListedInEitherDocument(): void
    {
        foreach (HookType::cases() as $type) {
            self::assertStringNotContainsString('mahout/kernel/internal', $this->document($type));
        }
    }

    public function testAPublicConstantOutsideAHooksClassIsNotListedInEitherDocument(): void
    {
        foreach (HookType::cases() as $type) {
            self::assertStringNotContainsString('fixture/search_index', $this->document($type));
        }
    }

    public function testAPublicConstantOutsideAHooksClassNeedsNoHookTags(): void
    {
        $this->expectNotToPerformAssertions();

        $this->definitions();
    }

    public function testADocumentForAKindWithNoHooksIsStillRendered(): void
    {
        // A package that declares only actions must still have a filters document, or the
        // gate for that file would compare nothing and a deletion would go unnoticed.
        $empty = (new HookDocument(HookType::Filter, []))->generate();

        self::assertStringContainsString('# Filter hooks', $empty);
        self::assertStringContainsString('declares no filters', $empty);
    }

    public function testGenerationIsDeterministic(): void
    {
        foreach (HookType::cases() as $type) {
            self::assertSame($this->document($type), $this->document($type));
        }
    }

    public function testTheCommittedFixtureDocumentsAreCurrent(): void
    {
        $root = $this->root();

        foreach (HookType::cases() as $type) {
            $gate = new ReferenceGate($this->documentFor($root, $type), $root.'/fixtures/hooks/'.$type->fileName());

            self::assertTrue($gate->isCurrent(), $type->fileName().': '.$gate->firstDifference());
        }
    }

    /**
     * The mutated fixture renames a filter constant. It must therefore drift the filter
     * document, and must leave the action document current — the two files are independent
     * artefacts, and a gate that could not tell them apart would be one file again.
     */
    public function testAMutationToAFilterDriftsOnlyTheFilterDocument(): void
    {
        $root = $this->root();

        $filters = new ReferenceGate($this->documentFor($root, HookType::Filter, 'fixtures/hooks/mutated'), $root.'/fixtures/hooks/'.HookType::Filter->fileName());
        $actions = new ReferenceGate($this->documentFor($root, HookType::Action, 'fixtures/hooks/mutated'), $root.'/fixtures/hooks/'.HookType::Action->fileName());

        self::assertFalse($filters->isCurrent(), 'the renamed filter must drift filters.md');
        self::assertTrue($actions->isCurrent(), 'actions.md must be untouched by a filter change: '.$actions->firstDifference());
    }

    public function testTheMutatedFixtureRenamedAFilterAndLeftTheActionAlone(): void
    {
        // Guards the independence assertion above: if the fixture ever mutates an action
        // instead, that test would pass for the wrong reason.
        $definitions = (new HooksReference(new SourceFiles([$this->root().'/fixtures/hooks/mutated'], $this->root())))->definitions();

        $renamed = array_values(array_filter(
            $definitions,
            static fn (HookDefinition $definition): bool => HookType::Filter === $definition->type
                && 'mahout/kernel/renamed_providers' === $definition->hook,
        ));

        $actions = array_values(array_filter(
            $definitions,
            static fn (HookDefinition $definition): bool => HookType::Action === $definition->type,
        ));

        self::assertCount(1, $renamed, 'the fixture must mutate exactly one filter, or the independence test proves nothing');
        self::assertSame(
            ['mahout/kernel/before_boot'],
            array_map(static fn (HookDefinition $definition): string => $definition->hook, $actions),
            'the fixture must leave the action constant intact',
        );
    }

    public function testAnUndocumentedHookTypeIsRefused(): void
    {
        $this->expectException(InvalidHookDeclaration::class);

        $this->definitions('fixtures/hooks/invalid');
    }

    private function document(HookType $type): string
    {
        return (new HookDocument($type, $this->definitions()))->generate();
    }

    private function documentFor(string $root, HookType $type, string $source = 'fixtures/hooks/source'): HookDocument
    {
        $definitions = (new HooksReference(new SourceFiles([$root.'/'.$source], $root)))->definitions();

        return new HookDocument($type, $definitions);
    }

    /**
     * @return list<HookDefinition>
     */
    private function definitions(string $source = 'fixtures/hooks/source'): array
    {
        $root = $this->root();

        return (new HooksReference(new SourceFiles([$root.'/'.$source], $root)))->definitions();
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
