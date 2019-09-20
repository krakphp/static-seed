<?php

namespace Krak\StaticSeed;

use function Krak\Fun\{reduce, head};

class Table
{
    const ROW_TYPE_TUPLE = 'tuple';
    const ROW_TYPE_STRUCT = 'struct';

    public $name;
    public $type;
    public $row_type;
    public $index_by;
    public $fields;
    public $map_id;
    public $ignoreFieldsOnUpdate;
    public $primaryKey;
    public $rows;


    public function __construct($name, $row_type = self::ROW_TYPE_TUPLE, $fields = null) {
        $this->name = $name;
        $this->type = 'normal';
        $this->row_type = $row_type;
        $this->fields = $fields;
    }

    public function withMapId($map_id) {
        $table = clone $this;
        $table->map_id = $map_id;
        return $table;
    }

    public function hasTupleRows() {
        return $this->row_type == self::ROW_TYPE_TUPLE;
    }

    public function isJoin() {
        return $this->type == 'join';
    }

    public static function createJoin($name, $index_by = null, $map_id = null) {
        $table = new self($name);
        $table->type = 'join';
        $table->index_by = $index_by;
        $table->map_id = $map_id;
        return $table;
    }

    public static function createFromArray(array $data) {
        $table = new self($data['name']);
        foreach ($data as $key => $value) {
            $table->{$key} = $value;
        }
        return $table;
    }

    public function createFieldIndexMap() {
        return reduce(function($acc, $value, $idx) {
            $acc[$value] = $idx;
            return $acc;
        }, $this->fields);
    }

    public function getPrimaryKey(): string {
        return $this->primaryKey ?: head($this->fields);
    }

    public function getUpdateFields(): array {
        $ignoreFields = $this->ignoreFieldsOnUpdate;
        $primaryKey = $this->getPrimaryKey();
        return array_filter($this->fields, function(string $field) use ($ignoreFields, $primaryKey) {
            return !in_array($field, $this->ignoreFieldsOnUpdate) && $field !== $primaryKey;
        });
    }

    public function shouldTruncateTable(): bool {
        return $this->ignoreFieldsOnUpdate === null;
    }
}
