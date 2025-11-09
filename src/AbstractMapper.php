<?php

namespace DealNews\DB\History;

use \DealNews\DataMapper\Interfaces\Mapper;
use DealNews\DB\CRUD;
use Sarhan\Flatten\Flatten;

/**
 * Abstract Mapper class providing basic reusable functions
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DataMapper
 */
abstract class AbstractMapper extends \DealNews\DB\AbstractMapper {

    const REVISION_HISTORY_DATABASE_NAME = '';

    const REVISION_HISTORY_TABLE_NAME = 'revision_history';

    const REVISION_HISTORY_NAME = '';

    protected CRUD $history_crud;

    /**
     * @param CRUD|null $crud Primary CRUD connection used for regular persistence operations.
     * @param CRUD|null $history_crud Optional CRUD connection dedicated to revision history writes.
     */
    public function __construct(?CRUD $crud = null, ?CRUD $history_crud = null) {
        parent::__construct($crud);
        $this->history_crud = $history_crud ?? CRUD::factory(static::REVISION_HISTORY_DATABASE_NAME);
    }

    /**
     * Persist the provided object and automatically record the change in history.
     *
     * @param object $object Domain object being saved.
     *
     * @return object The saved object with any updates applied.
     */
    public function save($object): object {
        $id = $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]);
        if (!empty($id)) {
            $current = $this->load($id);
        } else {
            $current = new ($this::MAPPED_CLASS)();
        }
        $object = parent::save($object);
        $this->saveHistory($object, $current);

        return $object;
    }

    /**
     * Delete the record by id and log the removal in the revision history.
     *
     * @param int|string $id Primary key for the object being deleted.
     *
     * @return bool True when the delete succeeds, otherwise false.
     */
    public function delete($id): bool {
        $current = $this->load($id);
        $result  = parent::delete($id);
        if ($result) {
            $this->saveHistory(new ($this::MAPPED_CLASS)(), $current);
        }

        return $result;
    }

    /**
     * Persist a snapshot of the change to the revision history store.
     *
     * @param object $object  The new state of the record.
     * @param object $current The previous state of the record.
     *
     * @throws \RuntimeException When neither the old nor new object contains a primary key.
     *
     * @return void
     */
    protected function saveHistory(object $object, object $current): void {
        $new_id = $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]);
        $old_id = $this->getValue($current, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]);

        if (empty($new_id) && empty($old_id)) {
            throw new \RuntimeException('Object failed to save. History can not be saved.', 1);
        }

        if (empty($new_id)) {
            $object_id = $old_id;
            $status    = 'delete';
            $changes   = [
                'removed' => $current->toArray(),
            ];
        } elseif (empty($old_id)) {
            $object_id = $new_id;
            $status    = 'create';
            $changes   = [
                'added' => $current->toArray(),
            ];
        } else {
            $object_id  = $new_id;
            $status     = 'update';
            $changes    = $this->generateDiff($object, $current);
        }

        $this->history_crud->create(
            $this::REVISION_HISTORY_TABLE_NAME,
            [
                'object_id'   => $object_id,
                'object_type' => empty($this::REVISION_HISTORY_NAME) ? $this::MAPPED_CLASS : $this::REVISION_HISTORY_NAME,
                'object'      => json_encode($changes),
                'status'      => $status,
                'user'        => $this->getUser(),
            ]
        );
    }

    /**
     * Build a structured diff describing additions and removals between two objects.
     *
     * @param object $object  Newly saved record.
     * @param object $current Previously persisted record.
     *
     * @return array Associative array with `added` and `removed` keys describing the delta.
     */
    protected function generateDiff(object $object, object $current): array {
        $flatten    = new Flatten();
        $new_record = $flatten->flattenToArray($object->toArray());
        $old_record = $flatten->flattenToArray($current->toArray());

        return [
            'added'   => $flatten->unflattenToArray(array_diff($new_record, $old_record)),
            'removed' => $flatten->unflattenToArray(array_diff($old_record, $new_record)),
        ];
    }

    /**
     * Override this method to set the username of the user that made the change
     *
     * @return string
     */
    protected function getUser(): string {
        return 'unknown';
    }
}
