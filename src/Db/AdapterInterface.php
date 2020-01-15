<?php

namespace Realejo\Sdk\Db;

interface AdapterInterface
{
    public function fetchAll(array $where = [], array $order = [], int $count = null, int $offset = null);

    public function fetchRow(array $where = [], array $order = []);
}
