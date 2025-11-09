<?php

namespace DealNews\DB\History\Tests;

use DealNews\DB\History\AbstractMapper;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(AbstractMapper::class, 'generateDiff')]
class AbstractMapperGenerateDiffTest extends TestCase {

    /**
     * Ensures nested additions are preserved when generating diffs.
     */
    public function testGenerateDiffDetectsNestedAdditions(): void {
        $mapper  = new TestHistoryMapper();
        $current = new TestHistoryObject(
            [
                'details' => [
                    'tags' => ['php', 'legacy'],
                    'meta' => [
                        'created' => '2023-01-01',
                    ],
                ],
            ]
        );
        $object  = new TestHistoryObject(
            [
                'details' => [
                    'tags' => ['php', 'legacy', 'extra'],
                    'meta' => [
                        'created' => '2023-01-01',
                        'updated' => '2024-02-02',
                    ],
                ],
                'extra' => [
                    'notes' => 'Added later',
                ],
            ]
        );

        $diff = $mapper->callGenerateDiff($object, $current);

        $this->assertSame(
            [
                'details' => [
                    'tags' => [
                        2 => 'extra',
                    ],
                    'meta' => [
                        'updated' => '2024-02-02',
                    ],
                ],
                'extra' => [
                    'notes' => 'Added later',
                ],
            ],
            $diff['added']
        );
        $this->assertSame([], $diff['removed']);
    }

    /**
     * Ensures removed values are captured when generating diffs.
     */
    public function testGenerateDiffDetectsRemovals(): void {
        $mapper  = new TestHistoryMapper();
        $current = new TestHistoryObject(
            [
                'details' => [
                    'tags' => ['php', 'legacy', 'obsolete'],
                    'meta' => [
                        'created' => '2023-01-01',
                        'removed' => '2024-01-01',
                    ],
                ],
            ]
        );
        $object  = new TestHistoryObject(
            [
                'details' => [
                    'tags' => ['php'],
                    'meta' => [
                        'created' => '2023-01-01',
                    ],
                ],
            ]
        );

        $diff = $mapper->callGenerateDiff($object, $current);

        $this->assertSame([], $diff['added']);
        $this->assertSame(
            [
                'details' => [
                    'tags' => [
                        1 => 'legacy',
                        2 => 'obsolete',
                    ],
                    'meta' => [
                        'removed' => '2024-01-01',
                    ],
                ],
            ],
            $diff['removed']
        );
    }

    /**
     * Ensures scalar mutations are represented as added and removed values.
     */
    public function testGenerateDiffDetectsScalarChanges(): void {
        $mapper  = new TestHistoryMapper();
        $current = new TestHistoryObject(['status' => 'draft']);
        $object  = new TestHistoryObject(['status' => 'published']);

        $diff = $mapper->callGenerateDiff($object, $current);

        $this->assertSame(['status' => 'published'], $diff['added']);
        $this->assertSame(['status' => 'draft'], $diff['removed']);
    }
}

/**
 * Minimal mapper that exposes generateDiff for testing.
 */
class TestHistoryMapper extends AbstractMapper {

    public const DATABASE_NAME = '';

    public const TABLE = 'test_table';

    public const PRIMARY_KEY = 'id';

    public const MAPPED_CLASS = TestHistoryObject::class;

    public const MAPPING = [
        'id' => [
            'column' => 'id',
        ],
    ];

    /**
     * Overrides the parent constructor to avoid database setup.
     */
    public function __construct() {
        // Intentionally empty.
    }

    /**
     * Provides access to the protected generateDiff method.
     *
     * @param object $object  Updated object graph.
     * @param object $current Existing object graph.
     *
     * @return array{added: array<mixed>, removed: array<mixed>}
     */
    public function callGenerateDiff(object $object, object $current): array {
        return $this->generateDiff($object, $current);
    }
}

/**
 * Simple object that returns structured data for Flatten to process.
 */
class TestHistoryObject {

    /**
     * @var array<mixed>
     */
    protected array $data;

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array {
        return $this->data;
    }
}
