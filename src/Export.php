<?php

namespace Krak\StaticSeed;

use Doctrine\DBAL\Connection;
use Psr\Log;

/** exports a set of tables into files */
class Export
{
    private $conn;

    public function __construct(Connection $conn) {
        $this->conn = $conn;
    }

    public function export($tables, $out_dir, Log\LoggerInterface $logger = null) {
        $logger = $logger ?: new Log\NullLogger();
        foreach ($tables as $table) {
            $this->exportTable($table, $out_dir, $logger);
        }
    }

    public function exportTable(Table $table, $out_dir, Log\LoggerInterface $logger = null) {
        $logger = $logger ?: new Log\NullLogger();
        $table = clone $table;

        $logger->info("Exporting Table: {$table->name}");

        $logger->debug('Retrieving table data');
        $rows = $this->retrieveTableData($table);

        if (!$rows) {
            $logger->notice("No rows found, could not export table.");
            return;
        }

        if ($table->isJoin()) {
            $rows = $this->removeIdFromRows($rows);
        }

        $table->fields = array_keys($rows[0]);
        if ($table->map_id) {
            $rows = $this->mapRowIds($rows, $table->map_id);
        }
        if ($table->isJoin() && $table->index_by) {
            $rows = $this->indexRows($rows, $table->index_by);
        } else if ($table->row_type == Table::ROW_TYPE_TUPLE) {
            $rows = array_map('array_values', $rows);
        }
        $table->rows = $rows;

        $filepath = sprintf("%s/%s.json", $out_dir, $table->name);

        $logger->debug('Writing file: ' . $filepath);
        $encoded = json_encode($table, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($filepath, $encoded);
    }

    private function retrieveTableData(Table $table) {
        $stmt = $this->conn->query("SELECT * FROM {$table->name} ORDER BY id");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function removeIdFromRows($rows) {
        return array_map(function($row) {
            unset($row['id']);
            return $row;
        }, $rows);
    }

    private function indexRows($rows, $index_by) {
        $other_key = null;
        foreach ($rows[0] as $key => $val) {
            if ($key == $index_by || $key == 'id') {
                continue;
            }

            $other_key = $key;
        }

        return array_reduce($rows, function($acc, $row) use ($index_by, $other_key) {
            $acc[$row[$index_by]][] = $row[$other_key];
            return $acc;
        }, []);
    }

    private function mapRowIds($rows, $map_id) {
        $map_data = $this->generateMap($map_id);
        return array_map(function($row) use ($map_data) {
            foreach ($map_data as $key => $id_map) {
                if (is_null($row[$key])) {
                    continue;
                }
                $row[$key] = $id_map[$row[$key]];
            }
            return $row;
        }, $rows);
    }

    /** generates the map from the map id field */
    private function generateMap($map_id) {
        $map_data = [];
        foreach ($map_id as $key => $info) {
            $table = $info['table'];
            $fields = is_array($info['field'])
                ? $info['field']
                : [$info['field']];

            $qb = $this->conn->createQueryBuilder();
            $qb->select("{$table}.id");
            $qb->from($table, $table);

            foreach ($fields as $field) {
                if (!isset($info['export_map']) || !array_key_exists($field, $info['export_map'])) {
                    $qb->addSelect($field);
                    continue;
                }

                // this allows the mapping of a field into something else just for export
                $export_field_map = $info['export_map'][$field];
                $qb->addSelect($export_field_map['field']);

                // optionally allow joins to map from other tables
                if (isset($export_field_map['join'])) {
                    foreach ($export_field_map['join'] as $join_table_name => list($join_alias, $condition)) {
                        $qb->innerJoin($table, $join_table_name, $join_alias, $condition);
                    }
                }
            }

            $data = $qb->execute()->fetchAll(\PDO::FETCH_NUM);

            $map_data[$key] = array_reduce($data, function($acc, $tup) use ($fields) {
                $acc[$tup[0]] = implode('-', array_filter(array_slice($tup, 1)));
                return $acc;
            }, []);
        }

        return $map_data;
    }
}
