<?php

namespace DealNews\DB\History\Tests;

use DealNews\DB\CRUD;
use DealNews\DB\History\AbstractMapper;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(AbstractMapper::class, 'saveHistory')]
class AbstractMapperSaveHistoryTest extends TestCase {

    public function testSaveHistoryThrowsWhenObjectIdsMissing(): void {
        $crud_spy = new TestHistoryCrudSpy();
        $mapper   = new SaveHistoryTestMapper($crud_spy);
        $object   = new SaveHistoryTestObject(null, ['status' => 'new']);
        $current  = new SaveHistoryTestObject(null, ['status' => 'missing']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Object failed to save. History can not be saved.');

        $mapper->callSaveHistory($object, $current);
    }

    public function testSaveHistoryRecordsDeleteEvent(): void {
        $crud_spy = new TestHistoryCrudSpy();
        $mapper   = new SaveHistoryTestMapper($crud_spy);
        $object   = new SaveHistoryTestObject(null, []);
        $current  = new SaveHistoryTestObject(42, ['title' => 'Legacy']);

        $mapper->callSaveHistory($object, $current);

        $call = $crud_spy->getLastCreateCall();
        $this->assertSame(SaveHistoryTestMapper::REVISION_HISTORY_TABLE_NAME, $call['table']);
        $this->assertSame(42, $call['data']['object_id']);
        $this->assertSame('custom_history', $call['data']['object_type']);
        $this->assertSame('delete', $call['data']['status']);
        $this->assertSame(
            ['removed' => $current->toArray()],
            json_decode($call['data']['object'], true)
        );
    }

    public function testSaveHistoryRecordsCreateEventWithDefaultObjectType(): void {
        $crud_spy = new TestHistoryCrudSpy();
        $mapper   = new SaveHistoryDefaultNameMapper($crud_spy);
        $object   = new SaveHistoryTestObject(99, ['title' => 'New']);
        $current  = new SaveHistoryTestObject(null, ['title' => 'Empty']);

        $mapper->callSaveHistory($object, $current);

        $call = $crud_spy->getLastCreateCall();
        $this->assertSame('create', $call['data']['status']);
        $this->assertSame(99, $call['data']['object_id']);
        $this->assertSame(SaveHistoryDefaultNameMapper::MAPPED_CLASS, $call['data']['object_type']);
        $this->assertSame(
            ['added' => $current->toArray()],
            json_decode($call['data']['object'], true)
        );
    }

    public function testSaveHistoryRecordsUpdateEventWithDiff(): void {
        $crud_spy = new TestHistoryCrudSpy();
        $mapper   = new SaveHistoryTestMapper($crud_spy);
        $mapper->setDiffResult(
            [
                'added'   => ['payload' => ['name' => 'new']],
                'removed' => ['payload' => ['name' => 'old']],
            ]
        );
        $object  = new SaveHistoryTestObject(7, ['payload' => ['name' => 'new']]);
        $current = new SaveHistoryTestObject(7, ['payload' => ['name' => 'old']]);

        $mapper->callSaveHistory($object, $current);

        $call = $crud_spy->getLastCreateCall();
        $this->assertSame('update', $call['data']['status']);
        $this->assertSame(7, $call['data']['object_id']);
        $this->assertSame(
            [
                'added'   => ['payload' => ['name' => 'new']],
                'removed' => ['payload' => ['name' => 'old']],
            ],
            json_decode($call['data']['object'], true)
        );
    }
}

/**
 * Mapper exposing saveHistory for unit testing.
 */
class SaveHistoryTestMapper extends AbstractMapper {

    public const REVISION_HISTORY_DATABASE_NAME = 'history_db';

    public const REVISION_HISTORY_TABLE_NAME = 'history_table';

    public const REVISION_HISTORY_NAME = 'custom_history';

    public const DATABASE_NAME = 'primary_db';

    public const TABLE = 'primary_table';

    public const PRIMARY_KEY = 'id_value';

    public const MAPPED_CLASS = SaveHistoryTestObject::class;

    public const MAPPING = [
        'id_value' => [
            'column' => 'id_value',
        ],
    ];

    /**
     * @var array{added: array<mixed>, removed: array<mixed>}
     */
    protected array $diff_result = [
        'added'   => [],
        'removed' => [],
    ];

    /**
     * @param CRUD $history_crud Spy instance for assertions.
     */
    public function __construct(CRUD $history_crud) {
        $this->history_crud = $history_crud;
    }

    /**
     * Proxy to the protected saveHistory method.
     *
     * @param object $object  Object that was persisted.
     * @param object $current Object prior to change.
     */
    public function callSaveHistory(object $object, object $current): void {
        $this->saveHistory($object, $current);
    }

    /**
     * Allows tests to control generateDiff output.
     *
     * @param array{added: array<mixed>, removed: array<mixed>} $diff_result
     */
    public function setDiffResult(array $diff_result): void {
        $this->diff_result = $diff_result;
    }

    /**
     * Simplified accessor honoring the parent signature.
     *
     * @param object                 $object   Source object.
     * @param string                 $property Property name to read.
     * @param array<string, mixed>   $mapping  Mapping metadata.
     *
     * @return mixed
     */
    protected function getValue(object $object, string $property, array $mapping) {
        return $object->$property ?? null;
    }

    /**
     * Returns the configured fake diff for assertions.
     *
     * @param object $object
     * @param object $current
     *
     * @return array{added: array<mixed>, removed: array<mixed>}
     */
    protected function generateDiff(object $object, object $current): array {
        return $this->diff_result;
    }
}

/**
 * Mapper variation that falls back to the mapped class for object type.
 */
class SaveHistoryDefaultNameMapper extends SaveHistoryTestMapper {

    public const REVISION_HISTORY_NAME = '';
}

/**
 * Simple data object for exercising saveHistory.
 */
class SaveHistoryTestObject {

    /**
     * @param int|string|null $id_value
     * @param array<mixed>    $payload
     */
    public function __construct(
        public int|string|null $id_value,
        public array $payload
    ) {
    }

    /**
     * @return array{id_value: int|string|null, payload: array<mixed>}
     */
    public function toArray(): array {
        return [
            'id_value' => $this->id_value,
            'payload'  => $this->payload,
        ];
    }
}

/**
 * CRUD spy capturing create calls.
 */
class TestHistoryCrudSpy extends CRUD {

    /**
     * @var array<int, array{table: string, data: array<string, mixed>}>
     */
    protected array $create_calls = [];

    /**
     * parent constructor requires a PDO connection, skip for tests.
     */
    public function __construct() {
    }

    /**
     * Records create calls for later inspection.
     *
     * @param string               $table Table name.
     * @param array<string, mixed> $data  Insert payload.
     *
     * @return bool
     */
    public function create(string $table, array $data): bool {
        $this->create_calls[] = [
            'table' => $table,
            'data'  => $data,
        ];

        return true;
    }

    /**
     * Retrieves the most recent create invocation.
     *
     * @return array{table: string, data: array<string, mixed>}
     */
    public function getLastCreateCall(): array {
        if (empty($this->create_calls)) {
            return [
                'table' => '',
                'data'  => [],
            ];
        }

        return $this->create_calls[count($this->create_calls) - 1];
    }
}
