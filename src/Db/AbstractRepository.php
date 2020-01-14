<?php

namespace Realejo\Sdk\Db;

use InvalidArgumentException;
use Laminas\Cache\Storage as CacheStorage;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Sql\Select;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayObject;
use Psr\Container\ContainerInterface;
use Realejo\Sdk\Cache\CacheService;

class AbstractRepository
{
    /**
     * Nome da tabela a ser usada
     * @var string
     */
    protected $tableName;

    /**
     * Define o nome da chave
     * @var string|array
     */
    protected $tableKey;

    /**
     * @var LaminasDbAdapter
     */
    protected $adapter;

    /**
     * @var LaminasDbAdapter|string
     */
    protected $dbAdapter = LaminasDbAdapter::class;

    /**
     * @var bool
     */
    protected $useCache = false;

    /**
     * @var CacheStorage\Adapter\Filesystem
     */
    protected $cache;

    /**
     * Campo a ser usado no <option>
     *
     * @var string
     */
    protected $htmlSelectOption = '{nome}';

    /**
     * Campos a serem adicionados no <option> como data
     *
     * @var string|array
     */
    protected $htmlSelectOptionData;

    /**
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * Retorna vários registros
     *
     * @param string|array $where OPTIONAL An SQL WHERE clause
     * @param string|array $order OPTIONAL An SQL ORDER clause.
     * @param int $count OPTIONAL An SQL LIMIT count.
     * @param int $offset OPTIONAL An SQL LIMIT offset.
     *
     * @return ArrayObject[] | null
     */
    public function findAll(array $where = [], array $order = [], int $count = null, int $offset = null): ?array
    {
        // Cria a assinatura da consulta
        $cacheKey = 'findAll'
            . $this->getUniqueCacheKey()
            . md5(
                $this->getDbAdapter()->getSelect($this->getWhere($where), $order, $count, $offset)->getSqlString(
                    $this->getDbAdapter()->getTableGateway()->getAdapter()->getPlatform()
                )
            );

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $findAll = $this->getDbAdapter()->fetchAll($where, $order, $count, $offset);

        // Grava a consulta no cache
        if ($this->getUseCache()) {
            $this->getCache()->setItem($cacheKey, $findAll);
        }

        return $findAll;
    }

    public function getUniqueCacheKey(): string
    {
        return str_replace('\\', '_', get_class($this));
    }

    public function getDbAdapter(): LaminasDbAdapter
    {
        if (is_string($this->dbAdapter)) {
            $this->dbAdapter = new $this->dbAdapter($this->tableName, $this->tableKey);
        }

        return $this->dbAdapter;
    }

    /**
     * @param LaminasDbAdapter|string $dbAdapter
     */
    public function setDbAdapter($dbAdapter): void
    {
        if (is_string($dbAdapter)) {
            unset($this->dbAdapter);
            $this->dbAdapter = $dbAdapter;
        } elseif ($dbAdapter instanceof LaminasDbAdapter) {
            $this->dbAdapter = $dbAdapter;
        } else {
            throw new \InvalidArgumentException('Db Adapter invalido');
        }
    }

    /**
     * Configura o cache
     *
     * @return CacheStorage\Adapter\Filesystem | CacheStorage\StorageInterface
     */
    public function getCache()
    {
        if (!isset($this->cache)) {
            $this->cache = $this->getServiceLocator()
                ->get(CacheService::class)
                ->getFrontend(str_replace('\\', DIRECTORY_SEPARATOR, get_class($this)));
        }

        return $this->cache;
    }

    /**
     * @param CacheStorage\StorageInterface $cache
     * @return self
     */
    public function setCache(CacheStorage\StorageInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    public function hasServiceLocator(): bool
    {
        return null !== $this->serviceLocator;
    }

    /**
     * @return ContainerInterface
     */
    public function getServiceLocator(): ContainerInterface
    {
        return $this->serviceLocator;
    }

    /**
     * @param ContainerInterface $serviceLocator
     * @return self
     */
    public function setServiceLocator(ContainerInterface $serviceLocator): self
    {
        $this->serviceLocator = $serviceLocator;

        return $this;
    }

    /**
     * Retorna se deve usar o cache
     * @return bool
     */
    public function getUseCache(): bool
    {
        return $this->useCache;
    }

    /**
     * Define se deve usar o cache
     * @param bool $useCache
     * @return self
     */
    public function setUseCache($useCache): self
    {
        $this->useCache = $useCache;
        return $this;
    }

    /**
     * Retorna um registro
     *
     * @param string|array $where OPTIONAL An SQL WHERE clause
     * @param string|array $order OPTIONAL An SQL ORDER clause.
     * @return null|ArrayObject
     */
    public function findOne($where = null, $order = null)
    {
        $where = $this->getWhere($where);

        // Define se é a chave da tabela, assim como é verificado no Mapper::fetchRow()
        if (is_numeric($where) || is_string($where)) {
            // Verifica se há chave definida
            if (empty($this->getDbAdapter()->getTableKey())) {
                throw new InvalidArgumentException('Chave não definida');
            }

            // Verifica se é uma chave múltipla ou com cast
            if (is_array($this->getDbAdapter()->getTableKey())) {
                // Verifica se é uma chave simples com cast
                if (count($this->getDbAdapter()->getTableKey()) !== 1) {
                    throw new InvalidArgumentException('Não é possível acessar chaves múltiplas informando apenas uma');
                }
                $where = [$this->getDbAdapter()->getTableKey(true) => $where];
            } else {
                $where = [$this->getDbAdapter()->getTableKey() => $where];
            }
        }

        // Cria a assinatura da consulta
        $cacheKey = 'findOne'
            . $this->getUniqueCacheKey()
            . md5(
                $this->getDbAdapter()->getSelect($where, $order)->getSqlString(
                    $this->getDbAdapter()->getTableGateway()->getAdapter()->getPlatform()
                )
            );

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $findOne = $this->getDbAdapter()->fetchRow($where, $order);

        // Grava a consulta no cache
        if ($this->getUseCache()) {
            $this->getCache()->setItem($cacheKey, $findOne);
        }

        return $findOne;
    }

    public function getWhere(array $where): array
    {
        return $where;
    }

    /**
     * Retorna vários registros associados pela chave
     *
     * @param string|array $where OPTIONAL An SQL WHERE clause
     * @param string|array $order OPTIONAL An SQL ORDER clause.
     * @param int $count OPTIONAL An SQL LIMIT count.
     * @param int $offset OPTIONAL An SQL LIMIT offset.
     *
     * @return ArrayObject[] | null
     */
    public function findAssoc($where = null, $order = null, $count = null, $offset = null): ?array
    {
        // Cria a assinatura da consulta
        $cacheKey = 'findAssoc'
            . $this->getUniqueCacheKey()
            . '_key' . $this->getDbAdapter()->getTableKey(true) . '_'
            . md5(
                $this->getDbAdapter()->getSelect($this->getWhere($where), $order, $count, $offset)->getSqlString(
                    $this->getDbAdapter()->getTableGateway()->getAdapter()->getPlatform()
                )
            );

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $fetchAll = $this->getDbAdapter()->fetchAll($this->getWhere($where), $order, $count, $offset);
        $findAssoc = [];
        if (!empty($fetchAll)) {
            foreach ($fetchAll as $row) {
                $findAssoc[$row[$this->getDbAdapter()->getTableKey(true)]] = $row;
            }
        }

        // Grava a consulta no cache
        if ($this->getUseCache()) {
            $this->getCache()->setItem($cacheKey, $findAssoc);
        }

        return $findAssoc;
    }

    /**
     * Retorna a consulta paginada
     *
     * @param string|array $where OPTIONAL An SQL WHERE clause
     * @param string|array $order OPTIONAL An SQL ORDER clause.
     * @param int $count OPTIONAL An SQL LIMIT count.
     * @param int $offset OPTIONAL An SQL LIMIT offset.
     *
     * @return Paginator|ArrayObject[]|null
     */
    public function findPaginated($where = null, $order = null, $count = null, $offset = null)
    {
        // Define a consulta
        if ($where instanceof Select) {
            $select = $where;
        } else {
            $select = $this->getDbAdapter()->getSelect($this->getWhere($where), $order, $count, $offset);
        }

        // Verifica se deve usar o cache
        $cacheKey = 'findPaginated'
            . $this->getUniqueCacheKey()
            . md5($select->getSqlString($this->getDbAdapter()->getTableGateway()->getAdapter()->getPlatform()));

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $resultSet = new HydratingResultSet(
            $this->getDbAdapter()->getHydrator(),
            $this->getDbAdapter()->getEntity()
        );
        $adapter = new DbSelect($select, $this->getDbAdapter()->getTableGateway()->getAdapter(), $resultSet);

        $findPaginated = new Paginator($adapter);

        // Verifica se deve usar o cache
        if ($this->getUseCache()) {
            $findPaginated->setCacheEnabled(true)->setCache($this->getCache());
        }

        // Configura o paginator
        $findPaginated->setPageRange($this->getPaginatorOptions()->getPageRange());
        $findPaginated->setCurrentPageNumber($this->getPaginatorOptions()->getCurrentPageNumber());
        $findPaginated->setItemCountPerPage($this->getPaginatorOptions()->getItemCountPerPage());

        return $findPaginated;
    }

    /**
     * @return PaginatorOptions
     */
    public function getPaginatorOptions()
    {
        if (!isset($this->paginatorOptions)) {
            $this->paginatorOptions = new PaginatorOptions();
        }

        return $this->paginatorOptions;
    }

    /**
     * Inclui um novo registro
     *
     * @param array $set Dados do registro
     *
     * @return int|array Chave do registro criado
     */
    public function create($set)
    {
        return $this->getDbAdapter()->insert($set);
    }

    /**
     * Altera um registro
     *
     * @param array $set Dados do registro
     * @param int|array $key Chave do registro a ser alterado
     *
     * @return int Quantidade de registro alterados
     */
    public function update($set, $key)
    {
        return $this->getDbAdapter()->update($set, $key);
    }

    /**
     * @return bool
     */
    public function getUseJoin()
    {
        return $this->getDbAdapter()->getUseJoin();
    }

    /**
     * @param bool $useJoin
     * @return ServiceAbstract
     */
    public function setUseJoin($useJoin)
    {
        $this->getDbAdapter()->setUseJoin($useJoin);
        return $this;
    }

    /**
     * Apaga o cache
     *
     * Não precisa apagar o cache dos metadata pois é o mesmo do serviço
     */
    public function cleanCache()
    {
        $this->getCache()->flush();
        $this->getDbAdapter()->getCache()->flush();
    }

    /**
     * @return bool
     */
    public function getAutoCleanCache()
    {
        return $this->getDbAdapter()->getAutoCleanCache();
    }

    /**
     * @param bool $autoCleanCache
     *
     * @return ServiceAbstract
     */
    public function setAutoCleanCache($autoCleanCache)
    {
        $this->getDbAdapter()->setAutoCleanCache($autoCleanCache);

        return $this;
    }

    public function getFromServiceLocator($class)
    {
        if (!$this->hasServiceLocator()) {
            return null;
        }

        if ($this->getServiceLocator() instanceof ServiceManager && !$this->getServiceLocator()->has($class)) {
            $newService = new $class();
            if (method_exists($newService, 'setServiceLocator')) {
                $newService->setServiceLocator($this->getServiceLocator());
            }
            $this->getServiceLocator()->setService($class, $newService);
        }

        return $this->getServiceLocator()->get($class);
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     */
    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * @return array|string
     */
    public function getTableKey()
    {
        return $this->tableKey;
    }

    /**
     * @param array|string $tableKey
     */
    public function setTableKey($tableKey): void
    {
        $this->tableKey = $tableKey;
    }
}
