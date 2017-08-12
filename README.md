# Static Seed

Static Seed provides a way to manage lookup table data in a reliable and maintainable away. Obviating the need for complex migration files or custom synchronization scripts, static seed allows you to define your table information in JSON and import/export into and from your database.

## Installation

Install with composer at `krak/static-seed`

## Usage

```
<?php

use Krak\StaticSeed;

const SEED_DIR = __DIR__ . '/path/to/seed/dir';

/** Setup the export tables */
function export(StaticSeed\Export $export) {
    $export->export([
        new StaticSeed\Table('colors', 'tuple'),
        new StaticSeed\Table('color_sets', 'struct'),
        StaticSeed\Table::createJoin('color_sets_colors', 'color_set_id', [
            'color_set_id' => ['table' => 'color_sets', 'field' => 'slug'],
            'color_id' => ['table' => 'colors', 'field' => 'slug'],
        ])
    ], SEED_DIR);
}

function import(StaticSeed\Import $import) {
    $import->import(SEED_DIR);
}

function usage($argv) {
    printf("usage: %s <export|import>\n", $argv[0]);
    return 1;
}

function main($argv) {
    if (count($argv) <= 1) {
        exit(usage($argv));
    }

    $conn = myDoctrineDbalConnection();

    if ($argv[1] == 'export') {
        export(new StaticSeed\Export($conn));
    } else if ($argv[1] == 'import') {
        import(new StaticSeed\Import($conn));
    } else {
        exit(usage($argv));
    }
}

main($argv);
```

Exporting will generate a set of files that look like this:

- colors.json
- colors_sets.json
- colors_sets_colors.json

colors.json:

```json
{
    "name": "colors",
    "row_type": "tuple",
    "type": "normal",
    "fields": ["id", "name", "slug", "hex", "sort", "type"],
    "rows": [
        [1, "Red", "red", "#ff0000", 0, "normal"],
        [2, "Green", "green", "#00ff00", 0, "normal"],
        [3, "Blue", "blue", "#0000ff", 0, "normal"],
    ]
}

```

color_sets.json:

```json
{
    "name": "color_sets",
    "row_type": "struct",
    "type": "normal",
    "fields": ["id", "name", "slug"],
    "rows": [
        {"id": 1, "name": "Color Set 1", "slug": "color-set-1"},
        {"id": 1, "name": "Color Set 2", "slug": "color-set-2"}
    ]
}
```

color_sets_colors.json:

```json
{
    "name": "color_sets_colors",
    "type": "join",
    "row_type": "tuple",
    "index_by": "color_set_id",
    "fields": [
        "color_set_id",
        "color_id"
    ],
    "map_id": {
        "color_set_id": {
            "table": "color_sets",
            "field": "slug"
        },
        "color_id": {
            "table": "colors",
            "field": "slug"
        }
    },
    "rows": {
        "color-set-1": [
            "red",
            "green",
        ],
        "color-set-2": [
            "green",
            "blue"
        ]
    }
}
```
