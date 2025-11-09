<?php

namespace DealNews\DB\History\Tests;

use DealNews\DB\CRUD;
use DealNews\DB\History\AbstractMapper;
use DealNews\DB\PDO as DealNewsPDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractMapper::class)]
class AbstractMapperFunctionalTest extends TestCase {

    private DealNewsPDO $pdo;

    private CRUD $crud;

    private FunctionalHistoryMapper $mapper;

    protected function setUp(): void {
        parent::setUp();

        $this->pdo = new DealNewsPDO('sqlite::memory:');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->applySchemaMigrations();

        $this->crud   = new CRUD($this->pdo);
        $this->mapper = new FunctionalHistoryMapper($this->crud, $this->crud);
    }

    public function testSavePersistsRecordAndLogsCreateEvent(): void {
        $object = new FunctionalHistoryObject(
            [
                'title'   => 'Initial Title',
                'payload' => [
                    'priority' => 'low',
                ],
                'status'  => 'draft',
            ]
        );

        $saved = $this->mapper->save($object);

        $this->assertGreaterThan(0, $saved->id);

        $history = $this->fetchHistory();
        $this->assertCount(1, $history);

        $row = $history[0];
        $this->assertSame($saved->id, (int) $row['object_id']);
        $this->assertSame('create', $row['status']);
        $this->assertSame(FunctionalHistoryObject::class, $row['object_type']);
        $this->assertSame('functional-user', $row['user']);
        $this->assertSame(
            ['added' => (new FunctionalHistoryObject())->toArray()],
            json_decode($row['object'], true)
        );
    }

    public function testSaveUpdatesRecordAndLogsDiff(): void {
        $object = new FunctionalHistoryObject(
            [
                'title'   => 'Draft Title',
                'payload' => [
                    'priority' => 'low',
                ],
                'status'  => 'draft',
            ]
        );

        $object = $this->mapper->save($object);
        $object->title   = 'Published Title';
        $object->payload = [
            'priority' => 'high',
            'notes'    => 'urgent',
        ];
        $object->status  = 'published';

        $object = $this->mapper->save($object);

        $history = $this->fetchHistory();
        $this->assertCount(2, $history);

        $row = $history[1];
        $this->assertSame('update', $row['status']);
        $diff = json_decode($row['object'], true);
        $this->assertSame(
            [
                'title'   => 'Published Title',
                'payload' => [
                    'priority' => 'high',
                    'notes'    => 'urgent',
                ],
                'status'  => 'published',
            ],
            $diff['added']
        );
        $this->assertSame(
            [
                'title'   => 'Draft Title',
                'payload' => [
                    'priority' => 'low',
                ],
                'status'  => 'draft',
            ],
            $diff['removed']
        );
    }

    public function testDeleteRemovesRecordAndLogsHistory(): void {
        $object = new FunctionalHistoryObject(
            [
                'title'   => 'Legacy Title',
                'payload' => [
                    'priority' => 'low',
                ],
                'status'  => 'archived',
            ]
        );

        $object          = $this->mapper->save($object);
        $deletedSnapshot = $object->toArray();

        $this->assertTrue($this->mapper->delete($object->id));
        $this->assertNull($this->mapper->load($object->id));

        $history = $this->fetchHistory();
        $this->assertCount(2, $history);

        $row = $history[1];
        $this->assertSame('delete', $row['status']);
        $this->assertSame(
            [
                'removed' => $deletedSnapshot,
            ],
            json_decode($row['object'], true)
        );
    }

    /**
     * Applies the shared revision history schema followed by mapper-specific tables.
     */
    private function applySchemaMigrations(): void {
        $schema = file_get_contents(__DIR__ . '/../schema/sqlite.sql');
        if ($schema === false) {
            $this->fail('Unable to load schema/sqlite.sql for functional tests.');
        }
        $this->pdo->exec($schema);
        $this->pdo->exec(
            <<<SQL
CREATE TABLE functional_objects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    payload TEXT NOT NULL,
    status TEXT NOT NULL
);
SQL
        );
    }

    /**
     * Fetches the revision_history table for assertions ordered by insertion.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchHistory(): array {
        return $this->crud->read('revision_history', [], order: 'revision_id ASC');
    }
}

/**
 * Mapper implementation backed by SQLite for exercising the abstract base class.
 */
class FunctionalHistoryMapper extends AbstractMapper {

    public const DATABASE_NAME = 'functional_db';

    public const TABLE = 'functional_objects';

    public const PRIMARY_KEY = 'id';

    public const MAPPED_CLASS = FunctionalHistoryObject::class;

    public const MAPPING = [
        'id'      => [],
        'title'   => [],
        'payload' => [
            'encoding'   => 'json',
            'json_assoc' => true,
        ],
        'status'  => [],
    ];

    protected function getUser(): string {
        return 'functional-user';
    }
}

/**
 * Data object persisted by FunctionalHistoryMapper.
 */
class FunctionalHistoryObject {

    public int $id = 0;

    public string $title = '';

    /**
     * @var array<mixed>
     */
    public array $payload = [];

    public string $status = 'draft';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = []) {
        foreach ($data as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    /**
     * @return array{id: int, title: string, payload: array<mixed>, status: string}
     */
    public function toArray(): array {
        return [
            'id'      => $this->id,
            'title'   => $this->title,
            'payload' => $this->payload,
            'status'  => $this->status,
        ];
    }
}
