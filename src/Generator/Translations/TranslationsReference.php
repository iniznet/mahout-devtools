<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator\Translations;

use Iniznet\Mahout\Devtools\Exception\FileMissing;
use Iniznet\Mahout\Devtools\Exception\InvalidScriptModuleDomain;
use Iniznet\Mahout\Devtools\Generator\GeneratedReference;
use Iniznet\Mahout\Devtools\Generator\SourceFiles;

/**
 * Renders the POT template for a text domain from the translation calls in the
 * source tree.
 *
 * The output is deliberately date-free: a POT that carries a creation timestamp
 * can never be byte-identical across two runs, and a drift gate that can never
 * pass is not a gate. The file is otherwise the standard gettext shape.
 *
 * @internal
 */
final readonly class TranslationsReference implements GeneratedReference
{
    /**
     * The gettext functions this plan uses, plus the no-op pair and its
     * translator. translate_nooped_plural is recognised but contributes no
     * entry of its own: its pair was already declared by _n_noop or _nx_noop.
     *
     * @var list<string>
     */
    private const array FUNCTIONS = [
        '__',
        '_e',
        '_n',
        '_x',
        '_nx',
        '_ex',
        '_n_noop',
        '_nx_noop',
        'translate_nooped_plural',
        'esc_html__',
        'esc_html_e',
        'esc_attr__',
        'esc_attr_e',
    ];

    private const string SCRIPT_MODULE_FUNCTION = 'wp_set_script_module_translations';

    public function __construct(
        private string $domain,
        private SourceFiles $files,
    ) {
    }

    public function kind(): string
    {
        return 'POT template';
    }

    public function generate(): string
    {
        $entries = [];
        foreach ($this->files->phpFiles() as $file) {
            foreach ($this->extract($file) as $key => $entry) {
                $entries[$key] = $entry;
            }
        }

        ksort($entries, SORT_STRING);

        $output = $this->header();
        foreach ($entries as $entry) {
            $output .= $this->render($entry);
        }

        return $output;
    }

    /**
     * @return array<string, array{msgid: string, plural: ?string, context: ?string, comment: ?string, references: list<string>}>
     */
    private function extract(string $file): array
    {
        $code = file_get_contents($file);
        if (false === $code) {
            throw FileMissing::at($file);
        }

        /** @var list<array{0: int, 1: string, 2: int}|string> $tokens */
        $tokens = token_get_all($code);
        $entries = [];
        $relative = $this->files->relative($file);
        $translator = null;
        $translatorEndLine = 0;
        $count = \count($tokens);

        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (\is_string($token)) {
                continue;
            }

            $identifier = $token[0];
            $text = $token[1];
            $line = $token[2];

            if (T_COMMENT === $identifier || T_DOC_COMMENT === $identifier) {
                if (str_contains($text, 'translators:')) {
                    $translator = $text;
                    $translatorEndLine = $line + substr_count($text, "\n");
                }
                continue;
            }

            if (T_STRING !== $identifier) {
                continue;
            }

            $function = strtolower($text);
            if (self::SCRIPT_MODULE_FUNCTION === $function) {
                $this->checkScriptModuleDomain($tokens, $index, $relative.':'.$line);
                continue;
            }
            if (!\in_array($function, self::FUNCTIONS, true)) {
                continue;
            }

            $shape = $this->shape($function);
            if (null === $shape) {
                continue;
            }

            $open = $this->nextMeaningful($tokens, $index + 1);
            if (null === $open || '(' !== $tokens[$open]) {
                continue;
            }

            $arguments = $this->arguments($tokens, $open)[0];

            $msgid = $this->literal($arguments[$shape['msgid']] ?? []);
            if (null === $msgid) {
                continue;
            }

            $domainIndex = $shape['domain'];
            $domainArgument = $arguments[$domainIndex] ?? null;
            if (null !== $domainArgument) {
                if ($this->literal($domainArgument) !== $this->domain) {
                    continue;
                }
            }

            $plural = $this->optionalLiteral($arguments, $shape['plural']);
            $context = $this->optionalLiteral($arguments, $shape['context']);
            $reference = $relative.':'.$line;
            $entry = [
                'msgid' => $msgid,
                'plural' => $plural,
                'context' => $context,
                'comment' => ($translatorEndLine === $line - 1) ? $this->cleanComment($translator) : null,
                'references' => [$reference],
            ];

            $key = ($context ?? '')."\x04".$msgid;
            if (isset($entries[$key])) {
                if (!\in_array($reference, $entries[$key]['references'], true)) {
                    $entries[$key]['references'][] = $reference;
                }
                if (null === $entries[$key]['comment'] && null !== $entry['comment']) {
                    $entries[$key]['comment'] = $entry['comment'];
                }
                continue;
            }

            $entries[$key] = $entry;
        }

        return $entries;
    }

    /**
     * The script-module API takes the text domain as its second argument. When
     * that argument is a literal that differs from the domain being extracted,
     * the module's strings can never be served, so the gate refuses.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function checkScriptModuleDomain(array $tokens, int $index, string $location): void
    {
        $open = $this->nextMeaningful($tokens, $index + 1);
        if (null === $open || '(' !== $tokens[$open]) {
            return;
        }

        $arguments = $this->arguments($tokens, $open)[0];
        $domain = $this->literal($arguments[1] ?? []);
        if (null !== $domain && $domain !== $this->domain) {
            throw InvalidScriptModuleDomain::found($domain, $this->domain, $location);
        }
    }

    /**
     * @return array{msgid: int, plural: ?int, context: ?int, domain: int}|null
     */
    private function shape(string $function): ?array
    {
        return match ($function) {
            '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' => ['msgid' => 0, 'plural' => null, 'context' => null, 'domain' => 1],
            '_x', '_ex' => ['msgid' => 0, 'plural' => null, 'context' => 1, 'domain' => 2],
            '_n' => ['msgid' => 0, 'plural' => 1, 'context' => null, 'domain' => 3],
            '_nx' => ['msgid' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4],
            '_n_noop' => ['msgid' => 0, 'plural' => 1, 'context' => null, 'domain' => 2],
            '_nx_noop' => ['msgid' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3],
            default => null,
        };
    }

    /**
     * @param list<list<array{0: int, 1: string, 2: int}|string>> $arguments
     */
    private function optionalLiteral(array $arguments, ?int $index): ?string
    {
        if (null === $index) {
            return null;
        }

        return $this->literal($arguments[$index] ?? []);
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextMeaningful(array $tokens, int $from): ?int
    {
        $count = \count($tokens);
        for ($index = $from; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (\is_array($token) && \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: list<list<array{0: int, 1: string, 2: int}|string>>, 1: int}
     */
    private function arguments(array $tokens, int $open): array
    {
        $depth = 1;
        $arguments = [];
        $current = [];
        $count = \count($tokens);
        $index = $open;

        for ($index = $open + 1; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (\is_string($token)) {
                if ('(' === $token || '[' === $token || '{' === $token) {
                    ++$depth;
                    $current[] = $token;
                    continue;
                }
                if (')' === $token || ']' === $token || '}' === $token) {
                    --$depth;
                    if (0 === $depth) {
                        if ([] !== $current) {
                            $arguments[] = $current;
                        }
                        break;
                    }
                    $current[] = $token;
                    continue;
                }
                if (',' === $token && 1 === $depth) {
                    $arguments[] = $current;
                    $current = [];
                    continue;
                }
                $current[] = $token;
                continue;
            }

            $current[] = $token;
        }

        return [$arguments, $index];
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function literal(array $tokens): ?string
    {
        $meaningful = [];
        foreach ($tokens as $token) {
            if (\is_array($token) && \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $meaningful[] = $token;
        }

        if (1 !== \count($meaningful)) {
            return null;
        }

        $only = $meaningful[0];
        if (!\is_array($only) || T_CONSTANT_ENCAPSED_STRING !== $only[0]) {
            return null;
        }

        return $this->decode($only[1]);
    }

    private function decode(string $raw): string
    {
        $inner = substr($raw, 1, -1);
        if (str_starts_with($raw, "'")) {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
        }

        return stripcslashes($inner);
    }

    private function cleanComment(?string $comment): ?string
    {
        if (null === $comment) {
            return null;
        }

        $text = preg_replace('#^/\\*+\\s*#', '', trim($comment)) ?? '';
        $text = preg_replace('#\\s*\\*+/$#', '', $text) ?? '';
        $text = trim($text);

        return '' === $text ? null : $text;
    }

    /**
     * @param array{msgid: string, plural: ?string, context: ?string, comment: ?string, references: list<string>} $entry
     */
    private function render(array $entry): string
    {
        $output = '';
        if (null !== $entry['comment']) {
            $output .= '#. '.$entry['comment'].PHP_EOL;
        }

        $references = $entry['references'];
        sort($references, SORT_STRING);
        foreach ($references as $reference) {
            $output .= '#: '.$reference.PHP_EOL;
        }

        if (null !== $entry['context']) {
            $output .= 'msgctxt "'.$this->escape($entry['context']).'"'.PHP_EOL;
        }

        $output .= 'msgid "'.$this->escape($entry['msgid']).'"'.PHP_EOL;

        if (null !== $entry['plural']) {
            $output .= 'msgid_plural "'.$this->escape($entry['plural']).'"'.PHP_EOL;
            $output .= 'msgstr[0] ""'.PHP_EOL.'msgstr[1] ""'.PHP_EOL;
        } else {
            $output .= 'msgstr ""'.PHP_EOL;
        }

        return $output.PHP_EOL;
    }

    private function escape(string $value): string
    {
        return addcslashes($value, "\\\"\n\r\t");
    }

    private function header(): string
    {
        return '# Copyright (C) Iniznet'.PHP_EOL
            .'# This file is distributed under the GPL-2.0-or-later license.'.PHP_EOL
            .'msgid ""'.PHP_EOL
            .'msgstr ""'.PHP_EOL
            .'"Content-Type: text/plain; charset=UTF-8\n"'.PHP_EOL
            .'"Content-Transfer-Encoding: 8bit\n"'.PHP_EOL
            .'"Language-Team: LANGUAGE <LL@li.org>\n"'.PHP_EOL
            .'"MIME-Version: 1.0\n"'.PHP_EOL
            .'"X-Domain: '.$this->domain.'\n"'.PHP_EOL
            .PHP_EOL;
    }
}
