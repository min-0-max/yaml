<?php

declare(strict_types=1);

namespace Purr\Yaml\Test\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Purr\Yaml\Exception\ParseException;
use Purr\Yaml\Exception\ReferenceException;
use Purr\Yaml\Exception\YamlException;
use Purr\Yaml\Node\AliasNode;
use Purr\Yaml\Node\CollectionStyle;
use Purr\Yaml\Node\MappingEntry;
use Purr\Yaml\Node\MappingEntryList;
use Purr\Yaml\Node\MappingNode;
use Purr\Yaml\Node\NodeList;
use Purr\Yaml\Node\ScalarNode;
use Purr\Yaml\Node\ScalarStyle;
use Purr\Yaml\Node\SequenceNode;
use Purr\Yaml\Node\Tag;
use Purr\Yaml\Parser\Parser;

#[CoversClass(Parser::class)]
final class ParserTest extends TestCase
{
    public function testParse_FlatMapping_ReturnsAnAssociativeArray(): void
    {
        $yaml = <<<'YAML'
            name: purrphp
            version: 1
            stable: true
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'name', line: 1),
                        value: new ScalarNode(value: 'purrphp', line: 1),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'version', line: 2),
                        value: new ScalarNode(value: '1', line: 2),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'stable', line: 3),
                        value: new ScalarNode(value: 'true', line: 3),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_BlockSequence_ReturnsAList(): void
    {
        $yaml = <<<'YAML'
            - one
            - two
            - three
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new ScalarNode(value: 'one', line: 1),
                    new ScalarNode(value: 'two', line: 2),
                    new ScalarNode(value: 'three', line: 3),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_NestedMappings_ReturnsNestedArrays(): void
    {
        $yaml = <<<'YAML'
            database:
              host: localhost
              credentials:
                user: root
                password: secret
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'database', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'host', line: 2),
                                    value: new ScalarNode(value: 'localhost', line: 2),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'credentials', line: 3),
                                    value: new MappingNode(
                                        entries: new MappingEntryList(
                                            new MappingEntry(
                                                key: new ScalarNode(value: 'user', line: 4),
                                                value: new ScalarNode(value: 'root', line: 4),
                                            ),
                                            new MappingEntry(
                                                key: new ScalarNode(value: 'password', line: 5),
                                                value: new ScalarNode(value: 'secret', line: 5),
                                            ),
                                        ),
                                        line: 4,
                                    ),
                                ),
                            ),
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_SequenceIndentedAtItsKey_ReturnsItAsTheValueOfThatKey(): void
    {
        $yaml = <<<'YAML'
            fruits:
            - apple
            - pear
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'fruits', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'apple', line: 2),
                                new ScalarNode(value: 'pear', line: 3),
                            ),
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_SequenceIndentedBelowItsKey_ReturnsItAsTheValueOfThatKey(): void
    {
        $yaml = <<<'YAML'
            fruits:
              - apple
              - pear
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'fruits', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'apple', line: 2),
                                new ScalarNode(value: 'pear', line: 3),
                            ),
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_MappingsInsideASequence_ReturnsAListOfArrays(): void
    {
        $yaml = <<<'YAML'
            - name: first
              size: 1
            - name: second
              size: 2
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new MappingNode(
                        entries: new MappingEntryList(
                            new MappingEntry(
                                key: new ScalarNode(value: 'name', line: 1),
                                value: new ScalarNode(value: 'first', line: 1),
                            ),
                            new MappingEntry(
                                key: new ScalarNode(value: 'size', line: 2),
                                value: new ScalarNode(value: '1', line: 2),
                            ),
                        ),
                        line: 1,
                    ),
                    new MappingNode(
                        entries: new MappingEntryList(
                            new MappingEntry(
                                key: new ScalarNode(value: 'name', line: 3),
                                value: new ScalarNode(value: 'second', line: 3),
                            ),
                            new MappingEntry(
                                key: new ScalarNode(value: 'size', line: 4),
                                value: new ScalarNode(value: '2', line: 4),
                            ),
                        ),
                        line: 3,
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_SequencesInsideASequence_ReturnsNestedLists(): void
    {
        $yaml = <<<'YAML'
            - - a
              - b
            - - c
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new SequenceNode(
                        new NodeList(
                            new ScalarNode(value: 'a', line: 1),
                            new ScalarNode(value: 'b', line: 2),
                        ),
                        line: 1,
                    ),
                    new SequenceNode(
                        new NodeList(
                            new ScalarNode(value: 'c', line: 3),
                        ),
                        line: 3,
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_SequenceItemWithoutContent_ReturnsNullForThatItem(): void
    {
        $yaml = <<<'YAML'
            -
            - filled
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new ScalarNode(value: '', line: 2),
                    new ScalarNode(value: 'filled', line: 2),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ValueWrittenBelowTheSequenceDash_ReturnsItAsTheItem(): void
    {
        $yaml = <<<'YAML'
            -
              name: first
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new MappingNode(
                        entries: new MappingEntryList(
                            new MappingEntry(
                                key: new ScalarNode(value: 'name', line: 2),
                                value: new ScalarNode(value: 'first', line: 2),
                            ),
                        ),
                        line: 2,
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_CommentsAndBlankLines_IgnoresThem(): void
    {
        $yaml = <<<'YAML'
            # leading comment

            name: purrphp   # trailing comment

            # another comment
            tags:
              - php     # first
              - yaml
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'name', line: 3),
                        value: new ScalarNode(value: 'purrphp', line: 3),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'tags', line: 6),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'php', line: 7),
                                new ScalarNode(value: 'yaml', line: 8),
                            ),
                            line: 7,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 3,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_SourceWithoutAnyDocument_ReturnsNull(): void
    {
        self::assertNull((new Parser)->parse('')->findFirst());
        self::assertNull((new Parser)->parse("# only a comment\n")->findFirst());
    }

    public function testParse_DocumentThatIsASingleScalar_ReturnsThatScalar(): void
    {
        $document = (new Parser)->parse('just a string')->findFirst();

        self::assertEquals(
            new ScalarNode(value: 'just a string', line: 1),
            $document?->getRoot(),
        );
    }

    public function testParse_KeyWithoutAValue_ReturnsNullForThatKey(): void
    {
        $yaml = <<<'YAML'
            first:
            second: 2
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'first', line: 1),
                        value: new ScalarNode(value: '', line: 2),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'second', line: 2),
                        value: new ScalarNode(value: '2', line: 2),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_QuotedKeys_KeepsTheirTextAsWritten(): void
    {
        $yaml = <<<'YAML'
            "with: colon": value
            'single': other
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'with: colon', style: ScalarStyle::DoubleQuoted, line: 1),
                        value: new ScalarNode(value: 'value', line: 1),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'single', style: ScalarStyle::SingleQuoted, line: 2),
                        value: new ScalarNode(value: 'other', line: 2),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_DeeplyNestedStructure_ReturnsTheWholeTree(): void
    {
        $yaml = <<<'YAML'
            services:
              web:
                ports:
                  - "8080:80"
                environment:
                  DEBUG: true
                  LEVEL: 3
              worker:
                command: run
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'services', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'web', line: 2),
                                    value: new MappingNode(
                                        entries: new MappingEntryList(
                                            new MappingEntry(
                                                key: new ScalarNode(value: 'ports', line: 3),
                                                value: new SequenceNode(
                                                    new NodeList(
                                                        new ScalarNode(value: '8080:80', style: ScalarStyle::DoubleQuoted, line: 4),
                                                    ),
                                                    line: 4,
                                                ),
                                            ),
                                            new MappingEntry(
                                                key: new ScalarNode(value: 'environment', line: 5),
                                                value: new MappingNode(
                                                    entries: new MappingEntryList(
                                                        new MappingEntry(
                                                            key: new ScalarNode(value: 'DEBUG', line: 6),
                                                            value: new ScalarNode(value: 'true', line: 6),
                                                        ),
                                                        new MappingEntry(
                                                            key: new ScalarNode(value: 'LEVEL', line: 7),
                                                            value: new ScalarNode(value: '3', line: 7),
                                                        ),
                                                    ),
                                                    line: 6,
                                                ),
                                            ),
                                        ),
                                        line: 3,
                                    ),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'worker', line: 8),
                                    value: new MappingNode(
                                        entries: new MappingEntryList(
                                            new MappingEntry(
                                                key: new ScalarNode(value: 'command', line: 9),
                                                value: new ScalarNode(value: 'run', line: 9),
                                            ),
                                        ),
                                        line: 9,
                                    ),
                                ),
                            ),
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_FlowSequence_ReturnsAList(): void
    {
        $document = (new Parser)->parse('list: [1, 2, 3]')->findFirst();

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
            $document?->getRoot(),
        );
    }

    public function testParse_FlowMapping_ReturnsAnAssociativeArray(): void
    {
        $document = (new Parser)->parse('point: {x: 1, y: 2}')->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'point', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'x', line: 1),
                                    value: new ScalarNode(value: '1', line: 1),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'y', line: 1),
                                    value: new ScalarNode(value: '2', line: 1),
                                ),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_EmptyFlowCollections_ReturnsEmptyArrays(): void
    {
        $yaml = <<<'YAML'
            list: []
            map: {}
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'list', line: 1),
                        value: new SequenceNode(
                            new NodeList,
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'map', line: 2),
                        value: new MappingNode(
                            entries: new MappingEntryList,
                            style: CollectionStyle::Flow,
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_NestedFlowCollections_ReturnsNestedArrays(): void
    {
        $document = (new Parser)->parse('matrix: [{a: 1}, {b: [2, 3]}]')->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'matrix', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new MappingNode(
                                    entries: new MappingEntryList(
                                        new MappingEntry(
                                            key: new ScalarNode(value: 'a', line: 1),
                                            value: new ScalarNode(value: '1', line: 1),
                                        ),
                                    ),
                                    style: CollectionStyle::Flow,
                                    line: 1,
                                ),
                                new MappingNode(
                                    entries: new MappingEntryList(
                                        new MappingEntry(
                                            key: new ScalarNode(value: 'b', line: 1),
                                            value: new SequenceNode(
                                                new NodeList(
                                                    new ScalarNode(value: '2', line: 1),
                                                    new ScalarNode(value: '3', line: 1),
                                                ),
                                                style: CollectionStyle::Flow,
                                                line: 1,
                                            ),
                                        ),
                                    ),
                                    style: CollectionStyle::Flow,
                                    line: 1,
                                ),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_QuotedValueInsideFlow_KeepsFlowDelimitersAsText(): void
    {
        $document = (new Parser)->parse('list: ["a, b", \'c\']')->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'list', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'a, b', style: ScalarStyle::DoubleQuoted, line: 1),
                                new ScalarNode(value: 'c', style: ScalarStyle::SingleQuoted, line: 1),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_FlowSpanningSeveralLines_ReturnsOneCollection(): void
    {
        $yaml = <<<'YAML'
            list: [
              one,
              two,
            ]
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'list', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'one', line: 1),
                                new ScalarNode(value: 'two', line: 1),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_FlowEntryWithoutAValue_ReturnsNullForThatEntry(): void
    {
        $document = (new Parser)->parse('map: {a, b: 1}')->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'map', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'a', line: 1),
                                    value: new ScalarNode(value: '', line: 1),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'b', line: 1),
                                    value: new ScalarNode(value: '1', line: 1),
                                ),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_BlockAndFlowCollections_RemembersTheStyleOfEachOne(): void
    {
        $document = (new Parser)->parse('point: {x: 1}')->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'point', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'x', line: 1),
                                    value: new ScalarNode(value: '1', line: 1),
                                ),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_FlowCollectionAsTheWholeDocument_ReturnsAList(): void
    {
        $document = (new Parser)->parse('[1, 2]')->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new ScalarNode(value: '1', line: 1),
                    new ScalarNode(value: '2', line: 1),
                ),
                style: CollectionStyle::Flow,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_LiteralBlock_KeepsItsLineBreaks(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                text: |
                  first
                  second
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "first\nsecond\n",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_FoldedBlock_TurnsLineBreaksIntoSpaces(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                text: >
                  first
                  second

                  new paragraph
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "first second\nnew paragraph\n",
                            style: ScalarStyle::Folded,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_BlockWithAStripIndicator_RemovesTheFinalLineBreak(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                text: |-
                  first
                  second
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "first\nsecond",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_BlockWithAKeepIndicator_KeepsEveryTrailingLineBreak(): void
    {
        $document = (new Parser)->parse("text: |+\n  first\n\n\nnext: 1")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "first\n\n\n",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'next', line: 5),
                        value: new ScalarNode(value: '1', line: 5),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_BlockWithAnExplicitIndentationIndicator_KeepsTheLeadingSpaces(): void
    {
        $document = (new Parser)->parse("text: |2\n    kept indent\n  outer\n")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "  kept indent\nouter\n",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_LiteralBlockWithDeeperLines_KeepsTheirIndentation(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                text: |
                  first
                    indented
                  last
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "first\n  indented\nlast\n",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_BlockScalarInsideASequence_ReturnsItAsTheItemValue(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                - |
                  first
                - second
                YAML
        )->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new ScalarNode(
                        value: "first\n",
                        style: ScalarStyle::Literal,
                        line: 1,
                    ),
                    new ScalarNode(value: 'second', line: 3),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_LiteralBlockWithEmptyLines_KeepsThem(): void
    {
        $document = (new Parser)->parse("text: |\n  first\n\n  third\n")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "first\n\nthird\n",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_HashInsideABlockScalar_KeepsItAsText(): void
    {
        $document = (new Parser)->parse("text: |\n  value # not a comment\n")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: "value # not a comment\n",
                            style: ScalarStyle::Literal,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_PlainScalarSpanningLines_JoinsContinuationLines(): void
    {
        $yaml = <<<'YAML'
            description: This is a long
              description that continues
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'description', line: 1),
                        value: new ScalarNode(
                            value: 'This is a long description that continues',
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_QuotedStringSpanningLines_FoldsItIntoOneLine(): void
    {
        $yaml = <<<'YAML'
            text: "first
              second"
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'text', line: 1),
                        value: new ScalarNode(
                            value: 'first second',
                            style: ScalarStyle::DoubleQuoted,
                            line: 1,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_CommentAfterAKeyWithANestedBlock_ReturnsTheNestedValue(): void
    {
        $yaml = <<<'YAML'
            parent:   # explains the block
              child: 1
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'parent', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'child', line: 2),
                                    value: new ScalarNode(value: '1', line: 2),
                                ),
                            ),
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_NestedBlockBelowASequenceItemKey_ReturnsTheItemStructure(): void
    {
        $yaml = <<<'YAML'
            - key:
                nested: 1
              other: 2
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new MappingNode(
                        entries: new MappingEntryList(
                            new MappingEntry(
                                key: new ScalarNode(value: 'key', line: 1),
                                value: new MappingNode(
                                    entries: new MappingEntryList(
                                        new MappingEntry(
                                            key: new ScalarNode(value: 'nested', line: 2),
                                            value: new ScalarNode(value: '1', line: 2),
                                        ),
                                    ),
                                    line: 2,
                                ),
                            ),
                            new MappingEntry(
                                key: new ScalarNode(value: 'other', line: 3),
                                value: new ScalarNode(value: '2', line: 3),
                            ),
                        ),
                        line: 1,
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_StandardAndCustomTags_KeepsThemOnTheirNodes(): void
    {
        $yaml = <<<'YAML'
            value: !!str 1
            custom: !point [1, 2]
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'value', line: 1),
                        value: new ScalarNode(
                            value: '1',
                            tag: Tag::fromString('!!str'),
                            line: 1,
                        ),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'custom', line: 2),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: '1', line: 2),
                                new ScalarNode(value: '2', line: 2),
                            ),
                            style: CollectionStyle::Flow,
                            tag: Tag::fromString('!point'),
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ReferenceToAnAnchoredScalar_ResolvesItToTheAnchoredValue(): void
    {
        $anchoredScalar = new ScalarNode(value: '42', anchor: 'value', line: 1);

        $document = (new Parser)->parse(
            <<<'YAML'
                original: &value 42
                copy: *value
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'original', line: 1),
                        value: $anchoredScalar,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'copy', line: 2),
                        value: new AliasNode(name: 'value', target: $anchoredScalar, line: 2),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ReferenceToAnAnchoredMapping_ResolvesItToTheAnchoredValue(): void
    {
        $defaultsMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'adapter', line: 2),
                    value: new ScalarNode(value: 'postgres', line: 2),
                ),
                new MappingEntry(
                    key: new ScalarNode(value: 'host', line: 3),
                    value: new ScalarNode(value: 'localhost', line: 3),
                ),
            ),
            anchor: 'defaults',
            line: 2,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                defaults: &defaults
                  adapter: postgres
                  host: localhost
                development: *defaults
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'defaults', line: 1),
                        value: $defaultsMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'development', line: 4),
                        value: new AliasNode(name: 'defaults', target: $defaultsMapping, line: 4),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ReferenceToAnAnchoredSequence_ResolvesItToTheAnchoredValue(): void
    {
        $listSequence = new SequenceNode(
            new NodeList(
                new ScalarNode(value: 'one', line: 2),
                new ScalarNode(value: 'two', line: 3),
            ),
            anchor: 'list',
            line: 2,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                base: &list
                  - one
                  - two
                copy: *list
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'base', line: 1),
                        value: $listSequence,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'copy', line: 4),
                        value: new AliasNode(name: 'list', target: $listSequence, line: 4),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_PropertiesBeforeSequenceItemOnTheSameLine_AttachesThemToTheSequence(): void
    {
        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new ScalarNode(value: 'one', line: 1),
                ),
                anchor: 'list',
                line: 1,
            ),
            (new Parser)->parse('&list - one')->findFirst()?->getRoot(),
        );

        self::assertEquals(
            new SequenceNode(
                new NodeList(
                    new ScalarNode(value: 'hello', line: 1),
                ),
                tag: Tag::fromString('!!str'),
                line: 1,
            ),
            (new Parser)->parse('!!str - hello')->findFirst()?->getRoot(),
        );

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'wrapper', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'one', line: 2),
                            ),
                            anchor: 'list',
                            line: 2,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            (new Parser)->parse("wrapper:\n  &list - one")->findFirst()?->getRoot(),
        );
    }

    public function testParse_ReferencesInsideBlockAndFlowCollections_ResolvesEachOfThem(): void
    {
        $itemScalar = new ScalarNode(value: 'value', anchor: 'item', line: 1);

        $document = (new Parser)->parse(
            <<<'YAML'
                anchor: &item value
                block:
                  - *item
                flow: [*item, *item]
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'anchor', line: 1),
                        value: $itemScalar,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'block', line: 2),
                        value: new SequenceNode(
                            new NodeList(
                                new AliasNode(name: 'item', target: $itemScalar, line: 3),
                            ),
                            line: 3,
                        ),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'flow', line: 4),
                        value: new SequenceNode(
                            new NodeList(
                                new AliasNode(name: 'item', target: $itemScalar, line: 4),
                                new AliasNode(name: 'item', target: $itemScalar, line: 4),
                            ),
                            style: CollectionStyle::Flow,
                            line: 4,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_AnchorDeclaredInsideAFlowCollection_MakesItAvailableToLaterReferences(): void
    {
        $sharedScalar = new ScalarNode(value: '7', anchor: 'shared', line: 1);

        $document = (new Parser)->parse(
            <<<'YAML'
                first: [&shared 7, *shared]
                second: *shared
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'first', line: 1),
                        value: new SequenceNode(
                            new NodeList(
                                $sharedScalar,
                                new AliasNode(name: 'shared', target: $sharedScalar, line: 1),
                            ),
                            style: CollectionStyle::Flow,
                            line: 1,
                        ),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'second', line: 2),
                        value: new AliasNode(name: 'shared', target: $sharedScalar, line: 2),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_MergeKeyPointingAtAnAnchoredMapping_AddsTheAnchoredKeys(): void
    {
        $defaultsMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'adapter', line: 2),
                    value: new ScalarNode(value: 'postgres', line: 2),
                ),
                new MappingEntry(
                    key: new ScalarNode(value: 'host', line: 3),
                    value: new ScalarNode(value: 'localhost', line: 3),
                ),
            ),
            anchor: 'defaults',
            line: 2,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                defaults: &defaults
                  adapter: postgres
                  host: localhost
                development:
                  <<: *defaults
                  database: dev
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'defaults', line: 1),
                        value: $defaultsMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'development', line: 4),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: '<<', line: 5),
                                    value: new AliasNode(name: 'defaults', target: $defaultsMapping, line: 5),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'database', line: 6),
                                    value: new ScalarNode(value: 'dev', line: 6),
                                ),
                            ),
                            line: 5,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_MergedKeyAlsoDefinedInTheMapping_KeepsTheOwnValue(): void
    {
        $defaultsMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'host', line: 2),
                    value: new ScalarNode(value: 'localhost', line: 2),
                ),
                new MappingEntry(
                    key: new ScalarNode(value: 'port', line: 3),
                    value: new ScalarNode(value: '5432', line: 3),
                ),
            ),
            anchor: 'defaults',
            line: 2,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                defaults: &defaults
                  host: localhost
                  port: 5432
                production:
                  <<: *defaults
                  host: db.example.com
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'defaults', line: 1),
                        value: $defaultsMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'production', line: 4),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: '<<', line: 5),
                                    value: new AliasNode(name: 'defaults', target: $defaultsMapping, line: 5),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'host', line: 6),
                                    value: new ScalarNode(value: 'db.example.com', line: 6),
                                ),
                            ),
                            line: 5,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_MergeKeyWithSeveralSources_LetsTheFirstSourceWin(): void
    {
        $firstMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'a', line: 2),
                    value: new ScalarNode(value: '1', line: 2),
                ),
                new MappingEntry(
                    key: new ScalarNode(value: 'b', line: 3),
                    value: new ScalarNode(value: '1', line: 3),
                ),
            ),
            anchor: 'first',
            line: 2,
        );

        $secondMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'b', line: 5),
                    value: new ScalarNode(value: '2', line: 5),
                ),
                new MappingEntry(
                    key: new ScalarNode(value: 'c', line: 6),
                    value: new ScalarNode(value: '2', line: 6),
                ),
            ),
            anchor: 'second',
            line: 5,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                first: &first
                  a: 1
                  b: 1
                second: &second
                  b: 2
                  c: 2
                result:
                  <<: [*first, *second]
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'first', line: 1),
                        value: $firstMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'second', line: 4),
                        value: $secondMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'result', line: 7),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: '<<', line: 8),
                                    value: new SequenceNode(
                                        new NodeList(
                                            new AliasNode(name: 'first', target: $firstMapping, line: 8),
                                            new AliasNode(name: 'second', target: $secondMapping, line: 8),
                                        ),
                                        style: CollectionStyle::Flow,
                                        line: 8,
                                    ),
                                ),
                            ),
                            line: 8,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_MergeKeyPointingAtAnAnchoredFlowMapping_AddsTheAnchoredKeys(): void
    {
        $defaultsMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'a', line: 1),
                    value: new ScalarNode(value: '1', line: 1),
                ),
            ),
            style: CollectionStyle::Flow,
            anchor: 'defaults',
            line: 1,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                defaults: &defaults {a: 1}
                result:
                  <<: *defaults
                  b: 2
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'defaults', line: 1),
                        value: $defaultsMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'result', line: 2),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: '<<', line: 3),
                                    value: new AliasNode(name: 'defaults', target: $defaultsMapping, line: 3),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'b', line: 4),
                                    value: new ScalarNode(value: '2', line: 4),
                                ),
                            ),
                            line: 3,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_DocumentWithAReference_LinksTheAliasToTheAnchoredNode(): void
    {
        $defaultsMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'a', line: 2),
                    value: new ScalarNode(value: '1', line: 2),
                ),
            ),
            anchor: 'defaults',
            line: 2,
        );

        $document = (new Parser)->parse(
            <<<'YAML'
                defaults: &defaults
                  a: 1
                copy: *defaults
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'defaults', line: 1),
                        value: $defaultsMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'copy', line: 3),
                        value: new AliasNode(name: 'defaults', target: $defaultsMapping, line: 3),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_NestedStructure_BuildsANavigableTree(): void
    {
        $yaml = <<<'YAML'
            server:
              host: localhost
              ports:
                - 80
                - 443
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'server', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode('host', line: 2),
                                    value: new ScalarNode(value: 'localhost', line: 2),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode('ports', line: 3),
                                    value: new SequenceNode(
                                        new NodeList(
                                            new ScalarNode(value: '80', line: 4),
                                            new ScalarNode(value: '443', line: 5),
                                        ),
                                        line: 4
                                    ),
                                )
                            ),
                            line: 2
                        )
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_NodeStartingOnALaterLine_KnowsItsSourceLine(): void
    {
        $yaml = <<<'YAML'
            first: 1
            second:
              - item
            YAML;

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'first', line: 1),
                        value: new ScalarNode(value: '1', line: 1),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'second', line: 2),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'item', line: 3),
                            ),
                            line: 3,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_CustomVisitorStructure_BuildsTheExpectedTree(): void
    {
        $yaml = <<<'YAML'
            base: &base
              a: 1
            copy: *base
            list:
              - x
              -
            YAML;

        $baseMapping = new MappingNode(
            entries: new MappingEntryList(
                new MappingEntry(
                    key: new ScalarNode(value: 'a', line: 2),
                    value: new ScalarNode(value: '1', line: 2),
                ),
            ),
            anchor: 'base',
            line: 2,
        );

        $document = (new Parser)->parse($yaml)->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'base', line: 1),
                        value: $baseMapping,
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'copy', line: 3),
                        value: new AliasNode(name: 'base', target: $baseMapping, line: 3),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'list', line: 4),
                        value: new SequenceNode(
                            new NodeList(
                                new ScalarNode(value: 'x', line: 5),
                                new ScalarNode(value: '', line: 0),
                            ),
                            line: 5,
                        ),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testAssertSupportedVersion_SourceWithYaml12Directive_ParsesTheDocument(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                %YAML 1.2
                ---
                key: value
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'key', line: 3),
                        value: new ScalarNode(value: 'value', line: 3),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 3,
            ),
            $document?->getRoot(),
        );
    }

    public function testAssertSupportedVersion_SourceWithYaml11Directive_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unsupported YAML version "1.1"; only 1.2 is supported');

        (new Parser)->parse("%YAML 1.1\n---\nkey: value\n");
    }

    public function testAssertSupportedVersion_SourceWithUnsupportedYamlVersion_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unsupported YAML version "1.3"; only 1.2 is supported');

        (new Parser)->parse("%YAML 1.3\nkey: value\n");
    }

    public function testAssertSupportedVersion_NonYamlDirective_IsIgnored(): void
    {
        $document = (new Parser)->parse(
            <<<'YAML'
                %TAG !yaml! tag:yaml.org,2002:
                key: value
                YAML
        )->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'key', line: 2),
                        value: new ScalarNode(value: 'value', line: 2),
                    ),
                ),
                style: CollectionStyle::Block,
                line: 2,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_LineThatIsNotAMappingEntry_ReportsTheOffendingLine(): void
    {
        try {
            (new Parser)->parse("a: 1\nb: 2\nnot a mapping entry\n");
            self::fail('A ParseException was expected.');
        } catch (ParseException $exception) {
            self::assertSame(3, $exception->line());
            self::assertStringContainsString('not a mapping entry', $exception->getMessage());
            self::assertStringContainsString('line 3', $exception->getMessage());
            self::assertInstanceOf(YamlException::class, $exception);
        }
    }

    public function testParse_TabUsedForIndentation_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Tabs are not allowed');

        (new Parser)->parse("root:\n\tkey: value");
    }

    public function testParse_UnterminatedFlowCollection_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);

        (new Parser)->parse('list: [1, 2');
    }

    public function testParse_UnterminatedQuotedString_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);

        (new Parser)->parse('value: "open');
    }

    public function testParse_UnknownEscapeSequence_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('escape sequence');

        (new Parser)->parse('value: "bad \q escape"');
    }

    public function testParse_InvalidBlockScalarHeader_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('block scalar header');

        (new Parser)->parse("value: |x\n  text");
    }

    public function testParse_ExplicitMappingKey_ReturnsTheEntry(): void
    {
        $document = (new Parser)->parse("? complex\n: value")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'complex', line: 1),
                        value: new ScalarNode(value: 'value', line: 2),
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ExplicitQuotedKey_ReadsItsEscapes(): void
    {
        $document = (new Parser)->parse("? \"a\\u2028b\"\n: 1\nc: 2")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: "a\u{2028}b", style: ScalarStyle::DoubleQuoted, line: 1),
                        value: new ScalarNode(value: '1', line: 2),
                    ),
                    new MappingEntry(
                        key: new ScalarNode(value: 'c', line: 3),
                        value: new ScalarNode(value: '2', line: 3),
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ExplicitKeyWithoutAValue_HasANullValue(): void
    {
        $document = (new Parser)->parse("outer:\n  ? lone\n  next: 1")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'outer', line: 1),
                        value: new MappingNode(
                            entries: new MappingEntryList(
                                new MappingEntry(
                                    key: new ScalarNode(value: 'lone', line: 2),
                                    value: ScalarNode::null(2),
                                ),
                                new MappingEntry(
                                    key: new ScalarNode(value: 'next', line: 3),
                                    value: new ScalarNode(value: '1', line: 3),
                                ),
                            ),
                            line: 2,
                        ),
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_ExplicitKeyWithABlockValue_ReadsTheBlockBelowTheColon(): void
    {
        $document = (new Parser)->parse("? k\n:\n  - 1")->findFirst();

        self::assertEquals(
            new MappingNode(
                entries: new MappingEntryList(
                    new MappingEntry(
                        key: new ScalarNode(value: 'k', line: 1),
                        value: new SequenceNode(items: new NodeList(new ScalarNode(value: '1', line: 3)), line: 3),
                    ),
                ),
                line: 1,
            ),
            $document?->getRoot(),
        );
    }

    public function testParse_AnchorWithoutAName_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('anchor must have a name');

        (new Parser)->parse('value: & 1');
    }

    public function testParse_ContentAfterAClosedFlowCollection_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected characters');

        (new Parser)->parse('value: [1, 2] trailing');
    }

    public function testParse_MisalignedContentBelowAValue_ThrowsParseException(): void
    {
        $this->expectException(ParseException::class);

        (new Parser)->parse("a: text\n  b: 1\n");
    }

    public function testParse_ReferenceToAnUndefinedAnchor_ThrowsReferenceException(): void
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('*missing');

        (new Parser)->parse('value: *missing');
    }

    public function testParse_ReferenceToAnAnchorOfAnotherDocument_ThrowsReferenceException(): void
    {
        $this->expectException(ReferenceException::class);

        (new Parser)->parse("---\na: &shared 1\n---\nb: *shared");
    }
}
