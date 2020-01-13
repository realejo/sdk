<?php

namespace RealejoTest\Service\Mapper;

use Realejo\Service\MapperAbstract;
use Laminas\Db\Sql\Select;

class MapperConcrete extends MapperAbstract
{
    protected $tableName = 'album';
    protected $tableKey = 'id';

    protected $tableJoin = [
        'test' => [
            'table' =>'test_table',
            'condition' => 'test_condition',
            'columns' => ['test_column'],
            'type' => Select::JOIN_LEFT
        ]
    ];
}
