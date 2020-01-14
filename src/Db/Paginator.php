<?php

/**
 * There is a bug when retrieve cache for adapter based on filesystem.
 * So, this class is used to override the _getCacheInternalId method
 *
 * https://github.com/zendframework/zend-paginator/issues/1
 * https://github.com/zendframework/zend-paginator/issues/41
 */

namespace Realejo\Sdk\Db;

use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator as LaminasPaginator;
use ReflectionObject;

class Paginator extends LaminasPaginator
{
    protected function _getCacheInternalId()
    {
        $adapter = $this->getAdapter();

        if ($adapter instanceof DbSelect) {
            $reflection = new ReflectionObject($adapter);

            /**
             * @var  $select Select
             */
            $property = $reflection->getProperty('select');
            $property->setAccessible(true);
            $select = $property->getValue($adapter);

            /**
             * @var  $sql Sql
             */
            $property = $reflection->getProperty('sql');
            $property->setAccessible(true);
            $sql = $property->getValue($adapter);

            return md5(
                $reflection->getName()
                . hash('sha512', $select->getSQLString($sql->getAdapter()->getPlatform()))
                . $this->getItemCountPerPage()
            );
        }

        return parent::_getCacheInternalId();
    }
}
