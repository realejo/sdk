<?php

namespace RealejoTest\Sdk\Db;

use Laminas\Db\Sql\Select;
use Realejo\Sdk\Db\AbstractRepository;

class RepositoryConcrete extends AbstractRepository
{
    protected $tableName = 'album';
    protected $tableKey = 'id';

    protected $tableJoin = [
        'test' => [
            'table' => 'test_table',
            'condition' => 'test_condition',
            'columns' => ['test_column'],
            'type' => Select::JOIN_LEFT
        ]
    ];
}
