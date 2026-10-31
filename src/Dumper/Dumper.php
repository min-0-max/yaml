<?php

declare(strict_types=1);

namespace Purr\Yaml\Dumper;

use function assert;

use Purr\Yaml\Document;
use Purr\Yaml\DocumentList;
use Purr\Yaml\Node\AbstractNode;
use Purr\Yaml\Node\AliasNode;
use Purr\Yaml\Node\CollectionStyle;
use Purr\Yaml\Node\MappingEntry;
use Purr\Yaml\Node\MappingNode;
use Purr\Yaml\Node\ScalarNode;
use Purr\Yaml\Node\ScalarStyle;
use Purr\Yaml\Node\SequenceNode;

use function strlen;

/**
 * Turns the parsed document model back into YAML text, preserving styles, tags and anchors.
 */
final class Dumper
{
    private const int INDENT_SIZE = 2;

    /** A character outside c-printable (YAML 1.2.2, 5.1), or a byte order mark. */
    private const string NOT_PRINTABLE = '/[^\t\n\r\x{20}-\x{7E}\x{85}\x{A0}-\x{D7FF}\x{E000}-\x{FEFE}\x{FF00}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

    public function dumpDocuments(DocumentList $documents): string
    {
        $parts = [];
        $first = true;

        foreach ($documents as $document) {
            if (!$first) {
                $parts[] = '---';
            }

            $first = false;
            $content = $this->dumpDocument($document);

            if ('' !== $content) {
                $parts[] = $content;
            }
        }

        return implode("\n", $parts);
    }

    public function dumpDocument(Document $document): string
    {
        return $this->dumpNode($document->getRoot(), 0);
    }

    private function dumpNode(AbstractNode $node, int $indent, bool $inFlow = false): string
    {
        return $this->attachProperties($node, $this->dumpNodeContent($node, $indent, $inFlow), $inFlow);
    }

    private function dumpNodeContent(AbstractNode $node, int $indent, bool $inFlow = false): string
    {
        return match (true) {
            $node instanceof ScalarNode => $this->dumpScalarContent($node, $indent, $inFlow),
            $node instanceof SequenceNode => $this->dumpSequenceContent($node, $indent, $inFlow),
            $node instanceof MappingNode => $this->dumpMappingContent($node, $indent, $inFlow),
            $node instanceof AliasNode => '*'.$node->getName(),
            default => '',
        };
    }

    private function dumpScalarContent(ScalarNode $node, int $indent, bool $inFlow): string
    {
        // A character outside c-printable can be written only as an escape, which only a double-quoted scalar has.
        if ($this->needsEscape($node->getValue())) {
            return '"'.$this->escapeDoubleQuoted($node->getValue()).'"';
        }

        return match ($node->getStyle()) {
            ScalarStyle::Plain => $this->dumpPlainScalar($node->getValue(), false, $inFlow),
            ScalarStyle::SingleQuoted => "'".$this->escapeSingleQuoted($node->getValue())."'",
            ScalarStyle::DoubleQuoted => '"'.$this->escapeDoubleQuoted($node->getValue()).'"',
            ScalarStyle::Literal => $this->dumpBlockScalar($node->getValue(), '|', $indent),
            ScalarStyle::Folded => $this->dumpBlockScalar($node->getValue(), '>', $indent),
        };
    }

    private function dumpPlainScalar(string $value, bool $isKey, bool $inFlow): string
    {
        if ('' === $value) {
            return '';
        }

        if ($this->needsEscape($value)) {
            return '"'.$this->escapeDoubleQuoted($value).'"';
        }

        if ($this->needsQuotes($value, $isKey, $inFlow)) {
            return "'".$this->escapeSingleQuoted($value)."'";
        }

        return $value;
    }

    /**
     * Whether the value holds a character outside c-printable (YAML 1.2.2, 5.1) or a byte order mark, which a stream holds
     * only as its first character (5.2).
     */
    private function needsEscape(string $value): bool
    {
        return 1 === preg_match(self::NOT_PRINTABLE, $value);
    }

    private function dumpBlockScalar(string $value, string $indicator, int $indent): string
    {
        [$header, $body] = $this->blockScalarParts($value, $indicator);
        $contentIndent = $indent + self::INDENT_SIZE;

        if ('>' === $indicator) {
            $lines = $this->foldedBodyLines($body);
        } else {
            $lines = '' === $body ? [] : explode("\n", $body);
        }

        $content = array_map(
            static fn (string $line): string => str_repeat(' ', $contentIndent).$line,
            $lines,
        );

        if ([] === $content) {
            return $header;
        }

        return $header."\n".implode("\n", $content);
    }

    /**
     * @return list<string>
     */
    private function foldedBodyLines(string $body): array
    {
        if ('' === $body) {
            return [];
        }

        $paragraphs = explode("\n", $body);
        $lines = [];

        foreach ($paragraphs as $index => $paragraph) {
            if ($index > 0) {
                $lines[] = '';
            }

            $lines[] = $paragraph;
        }

        return $lines;
    }

    /**
     * @return array{string, string}
     */
    private function blockScalarParts(string $value, string $indicator): array
    {
        if ('' === $value) {
            return [$indicator, ''];
        }

        if (!str_ends_with($value, "\n")) {
            return [$indicator.'-', $value];
        }

        $trailing = 0;
        $length = strlen($value);

        for ($position = $length - 1; $position >= 0 && "\n" === $value[$position]; --$position) {
            ++$trailing;
        }

        if (1 === $trailing) {
            return [$indicator, substr($value, 0, -1)];
        }

        return [$indicator.'+', substr($value, 0, -$trailing)];
    }

    private function dumpSequenceContent(SequenceNode $node, int $indent, bool $inFlow): string
    {
        if (CollectionStyle::Flow === $node->getStyle() || $inFlow) {
            $items = [];

            foreach ($node->getItems() as $item) {
                $items[] = $this->dumpNode($item, $indent, true);
            }

            return '['.implode(', ', $items).']';
        }

        $lines = [];

        foreach ($node->getItems() as $item) {
            $lines[] = $this->dumpBlockSequenceItem($item, $indent);
        }

        return implode("\n", $lines);
    }

    private function dumpBlockSequenceItem(AbstractNode $node, int $indent): string
    {
        $dash = $this->spaces($indent).'-';

        if ($node instanceof MappingNode && CollectionStyle::Block === $node->getStyle() && $node->count() > 0) {
            return $this->dumpDashMapping($node, $dash);
        }

        if ($this->isCompact($node)) {
            $content = $this->dumpNode($node, $indent, true);

            return '' === $content ? $dash : $dash.' '.$content;
        }

        return $dash."\n".$this->dumpNodeContent($node, $indent + 1);
    }

    private function dumpDashMapping(MappingNode $node, string $dash): string
    {
        $indent = strlen($dash) - strlen(ltrim($dash));
        $entries = $node->getEntries()->toArray();
        $first = array_shift($entries);
        assert($first instanceof MappingEntry);
        $lines = [$this->dumpMappingEntry($first, $indent + self::INDENT_SIZE, $dash.' ')];

        foreach ($entries as $entry) {
            $lines[] = $this->dumpMappingEntry($entry, $indent + self::INDENT_SIZE);
        }

        return implode("\n", $lines);
    }

    private function dumpMappingContent(MappingNode $node, int $indent, bool $inFlow): string
    {
        if (CollectionStyle::Flow === $node->getStyle() || $inFlow) {
            $entries = [];

            foreach ($node->getEntries() as $entry) {
                $entries[] = $this->dumpFlowMappingEntry($entry);
            }

            return '{'.implode(', ', $entries).'}';
        }

        $lines = [];

        foreach ($node->getEntries() as $entry) {
            $lines[] = $this->dumpMappingEntry($entry, $indent);
        }

        return implode("\n", $lines);
    }

    private function dumpFlowMappingEntry(MappingEntry $entry): string
    {
        $key = $this->dumpNode($entry->getKey(), 0, true);
        $value = $this->dumpNode($entry->getValue(), 0, true);

        if ('' === $value) {
            return $key.':';
        }

        return $key.': '.$value;
    }

    private function dumpMappingEntry(MappingEntry $entry, int $indent, string $prefix = ''): string
    {
        $key = $this->dumpNodeContent($entry->getKey(), $indent, true);
        $value = $entry->getValue();
        $lineStart = '' === $prefix ? $this->spaces($indent) : $prefix;

        if ($this->isCompact($value)) {
            $rendered = $this->dumpNode($value, $indent, true);

            return $lineStart.$key.('' === $rendered ? ':' : ': '.$rendered);
        }

        $valuePart = $this->dumpBlockMappingValue($value, $indent + 1);

        if (str_starts_with($valuePart, "\n")) {
            return $lineStart.$key.':'.$valuePart;
        }

        return $lineStart.$key.': '.$valuePart;
    }

    private function dumpBlockMappingValue(AbstractNode $value, int $indent): string
    {
        $properties = $this->formatProperties($value);
        $content = $this->dumpNodeContent($value, $indent);

        if ($value instanceof ScalarNode && $value->getStyle()->isBlock()) {
            return $properties.$content;
        }

        if ('' !== $properties) {
            return $properties."\n".$content;
        }

        return "\n".$content;
    }

    private function isCompact(AbstractNode $node): bool
    {
        return match (true) {
            $node instanceof ScalarNode => !$node->getStyle()->isBlock(),
            $node instanceof AliasNode => true,
            $node instanceof SequenceNode => CollectionStyle::Flow === $node->getStyle(),
            $node instanceof MappingNode => CollectionStyle::Flow === $node->getStyle(),
            default => false,
        };
    }

    private function attachProperties(AbstractNode $node, string $content, bool $inFlow): string
    {
        $properties = $this->formatProperties($node);

        if ('' === $properties) {
            return $content;
        }

        if ($inFlow) {
            return rtrim($properties).$content;
        }

        if (str_contains($content, "\n")) {
            return rtrim($properties)."\n".$content;
        }

        return rtrim($properties).$content;
    }

    private function formatProperties(AbstractNode $node): string
    {
        $parts = [];

        if (null !== ($tag = $node->findTag())) {
            $parts[] = $tag->getValue();
        }

        if (null !== ($anchor = $node->anchor())) {
            $parts[] = '&'.$anchor;
        }

        if ([] === $parts) {
            return '';
        }

        return implode(' ', $parts).' ';
    }

    private function needsQuotes(string $value, bool $isKey, bool $inFlow): bool
    {
        if ('' === $value) {
            return false;
        }

        if (preg_match('/(?:^\s|\s$)/', $value)) {
            return true;
        }

        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\t")) {
            return true;
        }

        // "-", "?" and ":" start a plain scalar unless a space or the end follows them (YAML 1.2.2, 7.3.3, ns-plain-first).
        if (preg_match('/^(?:[,\[\]{}#&*!|>\'"%@`]|[-?:](?:\s|$))/', $value)) {
            return true;
        }

        if (str_contains($value, ': ') || (!$isKey && str_ends_with($value, ':'))) {
            return true;
        }

        if ($inFlow && preg_match('/[,\[\]{}]/', $value)) {
            return true;
        }

        return false;
    }

    private function escapeSingleQuoted(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function escapeDoubleQuoted(string $value): string
    {
        return preg_replace_callback(
            '/[\t\n\r"\\\\\x{85}\x{A0}\x{2028}\x{2029}]|'.substr(self::NOT_PRINTABLE, 1, -2).'/u',
            static function (array $matches): string {
                $character = $matches[0];

                return match ($character) {
                    "\0" => '\0',
                    "\x07" => '\a',
                    "\x08" => '\b',
                    "\t" => '\t',
                    "\n" => '\n',
                    "\x0B" => '\v',
                    "\x0C" => '\f',
                    "\r" => '\r',
                    "\x1B" => '\e',
                    '"' => '\"',
                    '\\' => '\\\\',
                    "\u{85}" => '\N',
                    "\u{A0}" => '\_',
                    "\u{2028}" => '\L',
                    "\u{2029}" => '\P',
                    default => self::escapeCodePoint(mb_ord($character, 'UTF-8')),
                };
            },
            $value,
        ) ?? $value;
    }

    /** The escape of a code point: \x for one byte, \u for the Basic Multilingual Plane and \U above it. */
    private static function escapeCodePoint(int $point): string
    {
        return match (true) {
            $point <= 0xFF => sprintf('\x%02X', $point),
            $point <= 0xFFFF => sprintf('\u%04X', $point),
            default => sprintf('\U%08X', $point),
        };
    }

    private function spaces(int $indent): string
    {
        return str_repeat(' ', $indent);
    }
}
