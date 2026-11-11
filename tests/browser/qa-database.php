<?php

use Illuminate\Support\Facades\DB;

function useBrowserQaDatabase(string $database): void
{
    if (! preg_match('/^hives_best_[a-z_]*qa(?:_\d+)?$/', $database)) {
        throw new RuntimeException('Refusing a non-QA database name.');
    }
    $connection = config('database.default');
    if (! in_array($connection, ['mysql', 'mariadb'], true)) {
        throw new RuntimeException('Browser fixtures require a dedicated MySQL or MariaDB database.');
    }
    config(["database.connections.$connection.database" => $database]);
    DB::purge($connection);
    if (DB::selectOne('select database() as db')->db !== $database) {
        throw new RuntimeException('QA database isolation failed; no fixture writes allowed.');
    }
}
