<?php

namespace Krak\StaticSeed;

use Doctrine\DBAL\Connection;

class ImportTable
{
    public function importTable($filename) {
        $data = json_decode(file_get_contents($filename), true);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf("Failed parsing JSON file due to %s for file %s", json_last_error_msg(), $filename));
        }
        return Table::createFromArray($data);
    }
}
