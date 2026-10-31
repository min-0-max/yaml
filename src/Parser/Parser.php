<?php

declare(strict_types=1);

namespace Purr\Yaml\Parser;

use Purr\Yaml\Document;
use Purr\Yaml\DocumentList;
use Purr\Yaml\Exception\ParseException;
use Purr\Yaml\Node\AbstractNode;
use Purr\Yaml\Node\CollectionStyle;
use Purr\Yaml\Node\MappingEntry;
use Purr\Yaml\Node\MappingEntryList;
use Purr\Yaml\Node\MappingNode;
use Purr\Yaml\Node\NodeList;
use Purr\Yaml\Node\ScalarNode;
use Purr\Yaml\Node\ScalarStyle;
use Purr\Yaml\Node\SequenceNode;
use Purr\Yaml\SupportedVersion;

use function sprintf;
use function strlen;

/**
 * Turns YAML text into a list of documents built from {@see AbstractNode} objects.
 */
final class Parser
{
    public function __construct(
        private readonly BlockScalarParser $blockScalars = new BlockScalarParser,
        private readonly TextHelper $textHelper = new TextHelper,
    ) {}

    public function parse(string $input): DocumentList
    {
        $reader = LineReader::fromString($input);
        $documents = [];

        while (true) {
            $reader->skipIgnorable();
            $line = $reader->peek();

            if (null === $line) {
                break;
            }

            if ($line->isDirective()) {
                $this->assertSupportedVersion($line);
                $reader->next();

                continue;
            }

            $documents[] = $this->parseDocument($reader);
        }

        return new DocumentList(...$documents);
    }

    private function parseDocument(LineReader $reader): Document
    {
        $anchors = new AnchorRegistry;
        $line = $reader->peek();
        $startLine = $line?->getNumber() ?? 0;

        if (null !== $line && $line->isDocumentStart()) {
            $reader->next();
            $offset = $this->contentOffset($line, $line->getIndent() + 3);

            if (null !== $offset) {
                $reader->pushBack($line->slice($offset));
            }
        }

        $root = $this->parseBlockNode($reader, $anchors, 0);

        $reader->skipIgnorable();
        $next = $reader->peek();

        if (null !== $next && !$next->isDocumentBoundary()) {
            throw new ParseException(sprintf('Unable to parse "%s"', $next->getText()), $next->getNumber(), $next->getText());
        }

        if (null !== $next && $next->isDocumentEnd()) {
            $reader->next();
        }

        return new Document($root, $anchors->all(), $startLine);
    }

    /**
     * Parses the node that starts on the next content line, provided it is indented
     * by at least $minIndent.
     */
    private function parseBlockNode(LineReader $reader, AnchorRegistry $anchors, int $minIndent, ?NodeHeader $inherited = null): AbstractNode
    {
        $reader->skipIgnorable();
        $line = $reader->peek();

        if (null === $line || $line->isDocumentBoundary() || $line->getIndent() < $minIndent) {
            return $anchors->keep(ScalarNode::null($line?->getNumber() ?? 0, $inherited?->findAnchor(), $inherited?->findTag()));
        }

        $indent = $line->getIndent();
        $text = $this->textHelper->stripComment($line->getText());
        $own = NodeHeader::extract($text, $line->getNumber());
        $header = $own->inherit($inherited);
        $rest = $own->getRest();

        if ('' === $rest) {
            $reader->next();

            return $this->parseBlockNode($reader, $anchors, $indent, $header);
        }

        $opensCollection = $this->isSequenceItem($rest)
            || $this->isExplicitKey($rest)
            || null !== MappingKey::fromText($this->textHelper, $rest);

        if ($opensCollection && !$own->isEmpty()) {
            $reader->next();
            $reader->pushBack($line->slice($indent + strlen($text) - strlen($rest)));

            return $this->parseBlockNode($reader, $anchors, $indent, $header);
        }

        if ($opensCollection) {
            return $this->isSequenceItem($rest)
                ? $this->parseBlockSequence($reader, $anchors, $indent, $header)
                : $this->parseBlockMapping($reader, $anchors, $indent, $header);
        }

        $reader->next();

        return $this->parseScalarLike($reader, $anchors, $rest, $line, $minIndent, $header);
    }

    private function parseBlockSequence(LineReader $reader, AnchorRegistry $anchors, int $indent, NodeHeader $properties): AbstractNode
    {
        $items = [];
        $startLine = 0;

        while (true) {
            $reader->skipIgnorable();
            $line = $reader->peek();

            if (null === $line || $line->isDocumentBoundary() || $line->getIndent() !== $indent) {
                break;
            }

            if (!$this->isSequenceItem($this->textHelper->stripComment($line->getText()))) {
                break;
            }

            $startLine = 0 === $startLine ? $line->getNumber() : $startLine;
            $reader->next();
            $offset = $this->contentOffset($line, $indent + 1);

            if (null === $offset) {
                $items[] = $this->parseBlockNode($reader, $anchors, $indent + 1);

                continue;
            }

            $reader->pushBack($line->slice($offset));
            $items[] = $this->parseBlockNode($reader, $anchors, $offset);
        }

        return $anchors->keep(new SequenceNode(
            new NodeList(...$items),
            CollectionStyle::Block,
            $properties->findAnchor(),
            $properties->findTag(),
            $startLine,
        ));
    }

    private function parseBlockMapping(LineReader $reader, AnchorRegistry $anchors, int $indent, NodeHeader $properties): AbstractNode
    {
        $entries = [];
        $startLine = 0;

        while (true) {
            $reader->skipIgnorable();
            $line = $reader->peek();

            if (null === $line || $line->isDocumentBoundary() || $line->getIndent() !== $indent) {
                break;
            }

            $text = $this->textHelper->stripComment($line->getText());

            if ($this->isSequenceItem($text)) {
                break;
            }

            if ($this->isExplicitKey($text)) {
                $startLine = 0 === $startLine ? $line->getNumber() : $startLine;
                $entries[] = $this->parseExplicitEntry($reader, $anchors, $indent, $line, $text);

                continue;
            }

            $key = MappingKey::fromText($this->textHelper, $text);

            if (null === $key) {
                throw new ParseException(sprintf('Unable to parse "%s"', $text), $line->getNumber(), $text);
            }

            $startLine = 0 === $startLine ? $line->getNumber() : $startLine;
            $reader->next();

            $keyNode = (new InlineParser($anchors, $this->textHelper, $line->getNumber()))->parse($key->getKey());
            $value = '' === $key->getValue()
                ? $this->parseBlockValue($reader, $anchors, $indent)
                : $this->parseValueText($reader, $anchors, $key->getValue(), $line, $indent + 1);

            $entries[] = new MappingEntry($keyNode, $value);
        }

        return $anchors->keep(new MappingNode(
            new MappingEntryList(...$entries),
            CollectionStyle::Block,
            $properties->findAnchor(),
            $properties->findTag(),
            $startLine,
        ));
    }

    /**
     * Parses the text that follows a `key:` on the same line.
     */
    private function parseValueText(LineReader $reader, AnchorRegistry $anchors, string $text, Line $line, int $minIndent): AbstractNode
    {
        $header = NodeHeader::extract($text, $line->getNumber());

        if ('' === $header->getRest()) {
            return $this->parseBlockValue($reader, $anchors, $minIndent - 1, $header);
        }

        return $this->parseScalarLike($reader, $anchors, $header->getRest(), $line, $minIndent, $header);
    }

    /**
     * Parses a value written on the lines below its key, allowing a block sequence to
     * sit at the very indentation of the key it belongs to.
     */
    private function parseBlockValue(LineReader $reader, AnchorRegistry $anchors, int $parentIndent, ?NodeHeader $properties = null): AbstractNode
    {
        $reader->skipIgnorable();
        $line = $reader->peek();

        if (
            null !== $line
            && !$line->isDocumentBoundary()
            && $line->getIndent() === $parentIndent
            && $this->isSequenceItem($this->textHelper->stripComment($line->getText()))
        ) {
            return $this->parseBlockSequence($reader, $anchors, $parentIndent, $properties ?? NodeHeader::none());
        }

        return $this->parseBlockNode($reader, $anchors, $parentIndent + 1, $properties);
    }

    /**
     * Parses everything that is not a block collection: block scalars, flow collections,
     * quoted scalars, aliases and plain scalars.
     */
    private function parseScalarLike(LineReader $reader, AnchorRegistry $anchors, string $text, Line $line, int $minIndent, NodeHeader $properties): AbstractNode
    {
        $first = $text[0];

        if ('|' === $first || '>' === $first) {
            return $anchors->keep($this->blockScalars->parse($reader, $text, $minIndent, $properties, $line->getNumber()));
        }

        if ('*' === $first || '[' === $first || '{' === $first || '"' === $first || "'" === $first) {
            return (new InlineParser($anchors, $this->textHelper, $line->getNumber()))
                ->parse($this->joinUntilComplete($reader, $text, $line), $properties)
            ;
        }

        return $this->parsePlainScalar($reader, $anchors, $text, $line, $minIndent, $properties);
    }

    /**
     * Appends following lines while a flow collection or a quoted scalar stays open.
     */
    private function joinUntilComplete(LineReader $reader, string $text, Line $line): string
    {
        while (!$this->textHelper->isCompleted($text)) {
            $next = $reader->next();

            if (null === $next) {
                throw new ParseException('Unterminated flow collection or quoted string', $line->getNumber(), $text);
            }

            $text .= ' '.$this->textHelper->stripComment($next->getText());
        }

        return $text;
    }

    private function parsePlainScalar(LineReader $reader, AnchorRegistry $anchors, string $text, Line $line, int $minIndent, NodeHeader $properties): AbstractNode
    {
        $parts = [$text];

        while (null !== ($next = $reader->peek())) {
            if ($next->isBlank() || $next->isComment() || $next->isDocumentBoundary() || $next->getIndent() < $minIndent) {
                break;
            }

            $continuation = $this->textHelper->stripComment($next->getText());

            if ('' === $continuation || $this->isSequenceItem($continuation) || null !== MappingKey::fromText($this->textHelper, $continuation)) {
                break;
            }

            $parts[] = $continuation;
            $reader->next();
        }

        return $anchors->keep(new ScalarNode(
            implode(' ', $parts),
            ScalarStyle::Plain,
            $properties->findAnchor(),
            $properties->findTag(),
            $line->getNumber(),
        ));
    }

    /**
     * Offset of the first content character at or after $from, or null when only
     * whitespace and comments follow.
     */
    private function contentOffset(Line $line, int $from): ?int
    {
        $raw = $line->getRaw();
        $offset = $from;

        while (' ' === ($raw[$offset] ?? '')) {
            ++$offset;
        }

        if (!isset($raw[$offset]) || '' === rtrim($this->textHelper->stripComment(substr($raw, $offset)))) {
            return null;
        }

        return $offset;
    }

    private function isSequenceItem(string $text): bool
    {
        return '-' === ($text[0] ?? '') && ('' === substr($text, 1) || ' ' === $text[1]);
    }

    private function isExplicitKey(string $text): bool
    {
        return '?' === $text || str_starts_with($text, '? ');
    }

    /**
     * Parses an entry with an explicit key (YAML 1.2.2, 8.2.2): the key follows "? " on its line or is the block node
     * below it, and the value follows ": " on the next line of the mapping's indentation, or is the block node below a
     * lone ":". An entry without a ":" line has a null value.
     */
    private function parseExplicitEntry(LineReader $reader, AnchorRegistry $anchors, int $indent, Line $line, string $text): MappingEntry
    {
        $reader->next();
        $keyText = ltrim(substr($text, 1));
        $key = '' === $keyText
            ? $this->parseBlockNode($reader, $anchors, $indent + 1)
            : $this->parseValueText($reader, $anchors, $keyText, $line, $indent + 1);

        $reader->skipIgnorable();
        $next = $reader->peek();
        $valueText = null !== $next && !$next->isDocumentBoundary() && $next->getIndent() === $indent
            ? $this->textHelper->stripComment($next->getText())
            : null;

        if (null === $valueText || (':' !== $valueText && !str_starts_with($valueText, ': '))) {
            return new MappingEntry($key, $anchors->keep(ScalarNode::null($line->getNumber())));
        }

        $reader->next();
        $rest = ltrim(substr($valueText, 1));
        $value = '' === $rest
            ? $this->parseBlockValue($reader, $anchors, $indent)
            : $this->parseValueText($reader, $anchors, $rest, $next, $indent + 1);

        return new MappingEntry($key, $value);
    }

    private function assertSupportedVersion(Line $line): void
    {
        $text = $line->getText();

        if (!str_starts_with($text, '%YAML')) {
            return;
        }

        $version = trim(substr($text, 5));

        if (SupportedVersion::V_1_2 !== $version) {
            throw new ParseException(
                sprintf('Unsupported YAML version "%s"; only %s is supported', $version, SupportedVersion::V_1_2),
                $line->getNumber(),
                $text,
            );
        }
    }
}
