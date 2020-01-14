<?php

namespace Realejo\Sdk\Db;

interface AdapterInterface
{
    public function fetchAll(array $where = null, array $order = null, int $count = null, int $offset = null): ?array;

    public function fetchRow($where, $order = null);
}
