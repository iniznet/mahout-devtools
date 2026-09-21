<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Exception\InvalidScriptModuleDomain;
use Iniznet\Mahout\Devtools\Generator\ReferenceGate;
use Iniznet\Mahout\Devtools\Generator\SourceFiles;
use Iniznet\Mahout\Devtools\Generator\Translations\TranslationsReference;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * The POT generator is only real if it is deterministic, covers every
 * translation function the plan names, and can prove a stale file stale.
 *
 * @internal
 */
final class TranslationsReferenceTest extends TestCase
{
    public function testItRendersEveryFunctionThePlanUses(): void
    {
        $pot = $this->reference()->generate();

        foreach ([
            'Plain string',
            'Echoed string',
            '%s chapters',
            '%s pages',
            '%s stories',
            'Read',
            'Open %s',
            'Title attribute',
            'Label attribute',
            '%s files',
        ] as $needle) {
            self::assertStringContainsString($needle, $pot);
        }

        self::assertStringContainsString('msgctxt "verb"', $pot);
        self::assertStringContainsString('msgid_plural "%s chapters"', $pot);
        self::assertStringContainsString('msgstr[1] ""', $pot);
        self::assertStringContainsString('#. translators: %s: series title.', $pot);
    }

    public function testAModuleTranslationWithAForeignDomainIsRefused(): void
    {
        $this->expectException(InvalidScriptModuleDomain::class);

        $this->reference('fixtures/i18n/script-module')->generate();
    }

    public function testGenerationIsDeterministic(): void
    {
        self::assertSame($this->reference()->generate(), $this->reference()->generate());
    }

    public function testTheCommittedPotIsCurrent(): void
    {
        $root = $this->root();
        $gate = new ReferenceGate($this->reference(), $root.'/fixtures/i18n/howdah.pot');

        self::assertTrue($gate->isCurrent(), $gate->firstDifference());
    }

    public function testAMutatedSourceDrifts(): void
    {
        $root = $this->root();
        $gate = new ReferenceGate($this->reference('fixtures/i18n/mutated'), $root.'/fixtures/i18n/howdah.pot');

        self::assertFalse($gate->isCurrent());
    }

    private function reference(string $source = 'fixtures/i18n/source'): TranslationsReference
    {
        $root = $this->root();

        return new TranslationsReference('howdah', new SourceFiles([$root.'/'.$source], $root));
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
