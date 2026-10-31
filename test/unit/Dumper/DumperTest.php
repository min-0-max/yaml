<?php

declare(strict_types=1);

namespace Purr\Yaml\Test\Dumper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Purr\Yaml\Document;
use Purr\Yaml\DocumentList;
use Purr\Yaml\Dumper\Dumper;
use Purr\Yaml\Node\CollectionStyle;
use Purr\Yaml\Node\MappingEntry;
use Purr\Yaml\Node\MappingEntryList;
use Purr\Yaml\Node\MappingNode;
use Purr\Yaml\Node\NodeList;
use Purr\Yaml\Node\ScalarNode;
use Purr\Yaml\Node\ScalarStyle;
use Purr\Yaml\Node\SequenceNode;
use Purr\Yaml\Parser\Parser;
use Purr\Yaml\Schema\CoreSchema;

#[CoversClass(Dumper::class)]
final class DumperTest extends TestCase
{
    public function testDumpDocument_BlockMapping_RoundTripsItsValues(): void
    {
        $yaml = <<<'YAML'
            server:
              host: localhost
              ports:
                - 80
                - 443
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $roundTripped = $this->roundTripDocument($document);

        self::assertEquals($document->getRoot(), $roundTripped->getRoot());
    }

    public function testDumpDocuments_MultipleDocuments_SeparatesThemWithDocumentMarkers(): void
    {
        $source = <<<'YAML'
            ---
            name: first
            ---
            name: second
            YAML;

        $documents = (new Parser)->parse($source);
        $roundTripped = $this->roundTripDocuments($documents);

        $schema = new CoreSchema;

        self::assertSame([
            ['name' => 'first'],
            ['name' => 'second'],
        ], $documents->toPlainArray($schema));

        self::assertSame([
            ['name' => 'first'],
            ['name' => 'second'],
        ], $roundTripped->toPlainArray($schema));

        $first = $roundTripped->at(0);

        self::assertNotNull($first);
        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'name', line: 1),
                        value: new ScalarNode(value: 'first', line: 1),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $first->getRoot(),
        );

        $second = $roundTripped->at(1);
        self::assertNotNull($second);
        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'name', line: 3),
                        value: new ScalarNode(value: 'second', line: 3),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 3,
            ),
            $second->getRoot(),
        );
    }

    public function testDumpDocument_FlowCollections_PreservesTheirStyle(): void
    {
        $document = (new Parser)->parse('list: [1, 2, 3]')->findFirst();
        self::assertNotNull($document);

        $roundTripped = $this->roundTripDocument($document);

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'list', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: '1', line: 1),
                                new ScalarNode(value: '2', line: 1),
                                new ScalarNode(value: '3', line: 1),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $roundTripped->getRoot(),
        );
    }

    public function testDumpDocument_QuotedScalars_PreservesTheirStyle(): void
    {
        $yaml = <<<'YAML'
            plain: text
            quoted: "line one line two"
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $roundTripped = $this->roundTripDocument($document);

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'plain', line: 1),
                        value: new ScalarNode(value: 'text', line: 1),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'quoted', line: 2),
                        value: new ScalarNode(
                            value: 'line one line two',
                            style: ScalarStyle::DoubleQuoted,
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $roundTripped->getRoot(),
        );
    }

    public function testDumpDocument_AnchorsAndAliases_RoundTripsTheirGraph(): void
    {
        $yaml = <<<'YAML'
            defaults: &defaults
              adapter: postgres
              host: localhost
            development: *defaults
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $roundTripped = $this->roundTripDocument($document);

        self::assertEquals($document->getRoot(), $roundTripped->getRoot());
    }

    public function testDumpDocument_BlockScalars_RoundTripsTheirValues(): void
    {
        $yaml = <<<'YAML'
            literal: |
              first
              second
            folded: >
              first
              second

              new paragraph
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $roundTripped = $this->roundTripDocument($document);

        self::assertEquals($document->getRoot(), $roundTripped->getRoot());
    }

    public function testDumpDocument_TagsAndSpecialScalars_PreservesTheirRepresentation(): void
    {
        $yaml = <<<'YAML'
            tagged: !!str 42
            spaced: ' leading'
            empty: ''
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $output = (new Dumper)->dumpDocument($document);

        self::assertStringContainsString('!!str', $output);
        self::assertStringContainsString('42', $output);
        self::assertStringContainsString("' leading'", $output);
        self::assertStringContainsString('empty:', $output);
    }

    public function testDumpDocument_BlockScalarWithKeepIndicator_DumpsWithoutErrors(): void
    {
        $yaml = "text: |+\n  first\n\n\nnext: 1\n";

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $output = (new Dumper)->dumpDocument($document);

        self::assertStringContainsString('text:', $output);
        self::assertStringContainsString('first', $output);
        self::assertStringContainsString('next:', $output);
    }

    public function testDumpDocument_SequenceItemWithNestedMapping_RoundTripsDashMapping(): void
    {
        $yaml = <<<'YAML'
            - key:
                nested: 1
              other: 2
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();
        self::assertNotNull($document);

        $roundTripped = $this->roundTripDocument($document);

        self::assertEquals($document->getRoot(), $roundTripped->getRoot());
    }

    public function testDumpDocument_FlowMappingRoot_RendersInlineMapping(): void
    {
        $document = (new Parser)->parse('{x: 1, y: 2}')->findFirst();
        self::assertNotNull($document);

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame('{x: 1, y: 2}', $output);
    }

    public function testDumpDocument_FlowMappingEntryWithoutValue_RendersKeyWithColonOnly(): void
    {
        $document = (new Parser)->parse('{a, b: 1}')->findFirst();
        self::assertNotNull($document);

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame('{a:, b: 1}', $output);
    }

    public function testDumpDocument_DoubleQuotedScalar_EscapesControlCharacters(): void
    {
        $value = "\0\x07\x08\t\n\x0B\x0C\r\x1B\"\\"
            ."\xC2\x85\xC2\xA0"
            ."\xE2\x80\xA8\xE2\x80\xA9";

        $document = new Document(
            new ScalarNode($value, ScalarStyle::DoubleQuoted),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame(
            '"\0\a\b\t\n\v\f\r\e\"\\\\\N\_\L\P"',
            $output,
        );
    }

    public function testDumpDocument_DoubleQuotedScalar_EscapesEachNonPrintableCharacterByItsCodePoint(): void
    {
        $document = new Document(
            new ScalarNode("a\x7F\u{80}\u{9F}\u{FEFF}\u{FFFE}b", ScalarStyle::DoubleQuoted),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame('"a\x7F\x80\x9F\uFEFF\uFFFEb"', $output);
    }

    public function testDumpDocument_NonPrintableCharacterInAnotherStyle_WritesTheScalarDoubleQuoted(): void
    {
        $document = new Document(
            new MappingNode(new MappingEntryList(
                new MappingEntry(
                    new ScalarNode("k\x07", ScalarStyle::Plain),
                    new ScalarNode("v\u{9F}", ScalarStyle::SingleQuoted),
                ),
            )),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame('"k\a": "v\x9F"', $output);
    }

    public function testDumpDocument_PlainScalarsNeedingQuotes_WrapsInSingleQuotes(): void
    {
        $document = new Document(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode('hash'),
                        value: new ScalarNode('#comment'),
                    ),
                    new MappingEntry(
                        key: new ScalarNode('colon'),
                        value: new ScalarNode('a: b'),
                    ),
                    new MappingEntry(
                        key: new ScalarNode('suffix'),
                        value: new ScalarNode('ends:'),
                    ),
                    new MappingEntry(
                        key: new ScalarNode('flow'),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode('items'),
                                    value: new ScalarNode('has,comma'),
                                ),
                            ),
                            style: CollectionStyle::Flow,
                        ),
                    ),
                    new MappingEntry(
                        key: new ScalarNode('trailing'),
                        value: new ScalarNode('text '),
                    ),
                    new MappingEntry(
                        key: new ScalarNode('tabbed'),
                        value: new ScalarNode("has\ttab"),
                    ),
                ),
            ),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertStringContainsString("hash: '#comment'", $output);
        self::assertStringContainsString("colon: 'a: b'", $output);
        self::assertStringContainsString("suffix: 'ends:'", $output);
        self::assertStringContainsString("flow: {items: 'has,comma'}", $output);
        self::assertStringContainsString("trailing: 'text '", $output);
        self::assertStringContainsString("tabbed: 'has\ttab'", $output);
    }

    public function testDumpDocument_EmptyBlockScalar_RendersHeaderOnly(): void
    {
        $literal = new Document(new ScalarNode('', ScalarStyle::Literal));
        $folded = new Document(new ScalarNode('', ScalarStyle::Folded));

        $dumper = new Dumper;

        self::assertSame('|', $dumper->dumpDocument($literal));
        self::assertSame('>', $dumper->dumpDocument($folded));
    }

    public function testDumpDocument_LiteralBlockWithoutTrailingNewline_UsesStripIndicator(): void
    {
        $document = new Document(
            new ScalarNode("first\nsecond", ScalarStyle::Literal),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame("|-\n  first\n  second", $output);
    }

    public function testDumpDocument_AnchorOnFlowSequence_AttachesAnchorInline(): void
    {
        $document = new Document(
            new SequenceNode(
                items: new NodeList(
                    new ScalarNode('1'),
                    new ScalarNode('2'),
                ),
                style: CollectionStyle::Flow,
                anchor: 'anchor',
            ),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame('&anchor[1, 2]', $output);
    }

    public function testDumpDocument_AnchoredBlockScalar_AttachesPropertiesOnSeparateLine(): void
    {
        $document = new Document(
            new ScalarNode(
                value: "line\n",
                style: ScalarStyle::Literal,
                anchor: 'anchor',
            ),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame("&anchor\n|\n  line", $output);
    }

    public function testDumpDocument_BlockSequenceWithEmptyItem_RendersDashOnly(): void
    {
        $document = new Document(
            new SequenceNode(
                items: new NodeList(
                    ScalarNode::null(),
                    new ScalarNode('two'),
                ),
            ),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame("-\n- two", $output);
    }

    public function testDumpDocument_BlockSequenceWithMultilineItem_RendersNestedContent(): void
    {
        $document = new Document(
            new SequenceNode(
                items: new NodeList(
                    new ScalarNode("line\n", ScalarStyle::Literal),
                ),
            ),
        );

        $output = (new Dumper)->dumpDocument($document);

        self::assertSame("-\n|\n   line", $output);
    }

    public function testDumpDocuments_EmptyDocument_IsSkipped(): void
    {
        $documents = new DocumentList(
            new Document(ScalarNode::null()),
            new Document(
                new MappingNode(
                    entries: new MappingEntryList(
                        new MappingEntry(
                            key: new ScalarNode('name'),
                            value: new ScalarNode('second'),
                        ),
                    ),
                ),
            ),
        );

        $output = (new Dumper)->dumpDocuments($documents);

        self::assertSame("---\nname: second", $output);
    }

    private function roundTripDocument(Document $document): Document
    {
        $dumper = new Dumper;
        $roundTripped = (new Parser)->parse($dumper->dumpDocument($document))->findFirst();
        self::assertNotNull($roundTripped);

        return $roundTripped;
    }

    private function roundTripDocuments(DocumentList $documents): DocumentList
    {
        $dumper = new Dumper;

        return (new Parser)->parse($dumper->dumpDocuments($documents));
    }
}
