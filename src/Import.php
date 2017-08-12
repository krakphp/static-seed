<?php

namespace Krak\StaticSeed;

use Doctrine\DBAL\Connection;
use function iter\map, iter\filter, iter\toArray, iter\reduce, iter\join, iter\product, iter\flatten;

class Import
{
    private $conn;
    private $import_table;

    public function __construct(Connection $conn, ImportTable $import_table = null) {
        $this->conn = $conn;
        $this->import_table = $import_table ?: new ImportTable();
    }

    public function import($dir) {
        $iter = new \DirectoryIterator($dir);

        $files = filter(function($file) {
            return $file->isFile();
        }, $iter);
        $tables = map(function($file) {
            return $this->import_table->importTable($file->getPathname());
        }, $files);
        $tables_map = reduce(function($acc, $table) {
            $acc[$table->name] = $table;
            return $acc;
        }, $tables, []);

        $this->execSQL("SET foreign_key_checks = 0");

        foreach ($tables_map as $table) {
            if ($table->isJoin() && $table->index_by) {
                $this->importIndexedJoinTableSQL($table, $tables_map);
            }
            else if ($table->hasTupleRows()) {
                $this->importTupleRowsTableSQL($table, $tables_map);
            } else {
                $this->importStructRowsTableSQL($table, $tables_map);
            }
        }

        $this->execSQL("SET foreign_key_checks = 1");
    }

    private function importIndexedJoinTableSQL($table, $tables_map) {
        $rows = reduce(function($acc, $values, $key) use ($table) {
            $acc[] = $table->fields[0] == $table->index_by
                ? product([$key], $values)
                : product($values, [$key]);
            return $acc;
        }, $table->rows, []);
        $this->execInsertSQL($table, $tables_map, flatten($rows, 1));
    }

    private function importTupleRowsTableSQL($table, $tables_map) {
        $this->execInsertSQL($table, $tables_map, $table->rows);
    }

    private function importStructRowsTableSQL($table, $tables_map) {
        $this->execInsertSQL($table, $tables_map, map(function($row) use ($table) {
            return toArray(map(function($key) use ($row) {
                return array_key_exists($key, $row)
                    ? $row[$key]
                    : null;
            }, $table->fields));
        }, $table->rows));
    }

    private function execInsertSQL($table, $tables_map, $tuple_rows) {
        if ($table->map_id) {
            $tuple_rows = $this->mapTupleRowsWithMapId($tuple_rows, $table, $tables_map);
        }
        $sql = sprintf("INSERT INTO %s (%s) VALUES\n%s",
            $table->name,
            join(', ', $table->fields),
            join(",\n", map(function($row) {
                return '(' . join(', ', map(function($value) {
                    if (is_string($value)) {
                        return $this->conn->quote($value);
                    } else if (is_null($value)) {
                        return 'NULL';
                    }
                    return $value;
                }, $row)) . ')';
            }, $tuple_rows))
        );

        $this->execSQL("TRUNCATE TABLE {$table->name}");
        $this->execSQL($sql);
    }

    private function mapTupleRowsWithMapId($tuple_rows, $table, $tables_map) {
        $field_idx_map = $table->createFieldIndexMap();
        $id_maps = $this->generateIdMap($table->map_id, $tables_map);

        return map(function($row) use ($table, $id_maps, $field_idx_map) {
            foreach ($id_maps as $key => $id_map) {
                $orig_value = $row[$field_idx_map[$key]];
                if (is_null($orig_value)) {
                    continue;
                }
                if (!isset($id_map[$orig_value])) {
                    throw new \RuntimeException(sprintf('Could not map %s.%s of value %s with %s.%s', $table->name, $key, $orig_value, $table->map_id[$key]['table'], $table->map_id[$key]['field']));
                }

                $row[$field_idx_map[$key]] = $id_map[$orig_value];
            }
            return $row;
        }, $tuple_rows);
    }

    private function generateIdMap($map_id, $tables_map) {
        return reduce(function($acc, $map_info, $key) use ($tables_map) {
            $table = $tables_map[$map_info['table']];
            if ($table->isJoin()) {
                throw new \LogicException("Cannot use map info on join table");
            }

            $map_fields = is_array($map_info['field'])
                ? $map_info['field']
                : [$map_info['field']];

            $field_idx_map = $table->createFieldIndexMap();
            $acc[$key] = reduce(function($acc, $row) use ($table, $field_idx_map, $map_fields) {
                $idx = $this->createMapString(
                    $row,
                    $map_fields,
                    $table->hasTupleRows() ? $field_idx_map : null
                );
                $acc[$idx] = $table->hasTupleRows()
                    ? $row[$field_idx_map['id']]
                    : $row['id'];
                return $acc;
            }, $table->rows, []);

            return $acc;
        }, $map_id, []);
    }

    private function createMapString($row, $map_fields, $field_idx_map = null) {
        $fields = map(function($field) use ($row, $field_idx_map) {
            if ($field_idx_map) {
                $field = $field_idx_map[$field];
            }

            return $row[$field];
        }, $map_fields);

        return join('-', filter('boolval', $fields));
    }

    private function execSQL($sql) {
        $stmt = $this->conn->query($sql);
    }
}
