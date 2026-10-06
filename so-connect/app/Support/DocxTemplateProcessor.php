<?php

namespace App\Support;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * PhpWord's TemplateProcessor pinned to the app's `{{key}}` placeholder syntax.
 *
 * The delimiters have to be in place *before* the parent constructor runs. That
 * constructor calls `fixBrokenMacros()`, which stitches back together
 * placeholders Word split across XML runs — extremely common in real templates,
 * since spell-check and formatting marks chop a `{{full_name}}` into several
 * `<w:t>` nodes. Set the delimiters afterwards (via `setMacroChars()`) and the
 * repair pass has already run against PhpWord's `${key}` default, so a split
 * `{{full_name}}` is never rejoined and prints literally in the output.
 *
 * This matters for consistency with {@see \App\Helpers\FormTemplateHelper}:
 * placeholder *detection* at verify time already reassembles split runs, so
 * without this the template manager would report a placeholder as mapped and
 * generation would then fail to fill it.
 */
class DocxTemplateProcessor extends TemplateProcessor
{
    public const DEFAULT_OPENING = '{{';

    public const DEFAULT_CLOSING = '}}';

    /** PhpWord's own syntax, still accepted for interoperability. */
    public const LEGACY_OPENING = '${';

    public const LEGACY_CLOSING = '}';

    public function __construct(
        string $documentTemplate,
        string $openingChars = self::DEFAULT_OPENING,
        string $closingChars = self::DEFAULT_CLOSING,
    ) {
        // Static on the parent, so this also governs the constructor's repair.
        self::$macroOpeningChars = $openingChars;
        self::$macroClosingChars = $closingChars;

        parent::__construct($documentTemplate);
    }

    /**
     * Switch delimiter style and re-run the broken-macro repair for it, so a
     * template written in the other syntax gets the same split-run treatment
     * the constructor gave the primary one.
     */
    public function useMacroChars(string $openingChars, string $closingChars): void
    {
        self::$macroOpeningChars = $openingChars;
        self::$macroClosingChars = $closingChars;

        $this->tempDocumentMainPart = $this->fixBrokenMacros($this->tempDocumentMainPart);

        foreach ($this->tempDocumentHeaders as $index => $xml) {
            $this->tempDocumentHeaders[$index] = $this->fixBrokenMacros($xml);
        }

        foreach ($this->tempDocumentFooters as $index => $xml) {
            $this->tempDocumentFooters[$index] = $this->fixBrokenMacros($xml);
        }
    }

    public function macro(string $name): string
    {
        return self::$macroOpeningChars.$name.self::$macroClosingChars;
    }

    /**
     * Rewrite the main document part, every header and every footer.
     *
     * @param  callable(string): string  $transform
     */
    public function transformParts(callable $transform): void
    {
        $this->tempDocumentMainPart = $transform($this->tempDocumentMainPart);

        foreach ($this->tempDocumentHeaders as $index => $xml) {
            $this->tempDocumentHeaders[$index] = $transform($xml);
        }

        foreach ($this->tempDocumentFooters as $index => $xml) {
            $this->tempDocumentFooters[$index] = $transform($xml);
        }
    }

    /**
     * Raw contents of a package part as stored in the template (e.g.
     * `word/styles.xml`), or null when the part is absent.
     */
    public function packagePart(string $name): ?string
    {
        $contents = $this->zipClass->getFromName($name);

        return is_string($contents) ? $contents : null;
    }

    /**
     * Restore PhpWord's process-wide defaults — the delimiters are static, so
     * leaving them switched would leak into unrelated later use.
     */
    public static function resetMacroChars(): void
    {
        self::$macroOpeningChars = self::LEGACY_OPENING;
        self::$macroClosingChars = self::LEGACY_CLOSING;
    }
}
