<?php

namespace Realejo\Sdk\Db;

use Psr\Container\ContainerInterface;
use Realejo\Cache\CacheService;
use Realejo\Paginator\Paginator;
use Realejo\Stdlib\ArrayObject;
use Laminas\Cache\Storage as CacheStorage;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Sql\Select;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\ServiceManager\ServiceManager;

class AbstractRepository
{
    /**
     * @var LaminasDbAdapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $mapperClass = null;

    /**
     * @var boolean
     */
    protected $useCache = false;

    /**
     * @var Filesystem
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
    public function findAll(array $where = [], array $order = [], int $count = null, int $offset = null) :?array
    {
        // Cria a assinatura da consulta
        $cacheKey = 'findAll'
            . $this->getUniqueCacheKey()
            . md5(
                $this->getMapper()->getSelect($this->getWhere($where), $order, $count, $offset)->getSqlString(
                    $this->getMapper()->getTableGateway()->getAdapter()->getPlatform()
                )
            );

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $findAll = $this->getMapper()->fetchAll($where, $order, $count, $offset);

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

    public function getMapper(): MapperAbstract
    {
        if (!isset($this->mapper)) {
            if (!isset($this->mapperClass)) {
                throw new RuntimeException('Mapper class not defined at ' . get_class($this));
            }
            $this->mapper = new $this->mapperClass();
            $this->mapper->setCache($this->getCache());
            if ($this->hasServiceLocator()) {
                $this->mapper->setServiceLocator($this->getServiceLocator());
            }
        }

        return $this->mapper;
    }

    /**
     * @param MapperAbstract|string $mapper
     */
    public function setMapper($mapper):void
    {
        if (is_string($mapper)) {
            $this->mapperClass = $mapper;
            $this->mapper = null;
        } elseif ($mapper instanceof MapperAbstract) {
            $this->mapper = $mapper;
            $this->mapperClass = get_class($mapper);
        } else {
            throw new InvalidArgumentException('Mapper invalido em ' . get_class($this) . '::setMapper()');
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
     * @return ServiceAbstract
     */
    public function setCache(CacheStorage\StorageInterface $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    public function hasServiceLocator()
    {
        return null !== $this->serviceLocator;
    }

    /**
     * @return ContainerInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * @param ContainerInterface $serviceLocator
     * @return ServiceAbstract
     */
    public function setServiceLocator(ContainerInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;

        return $this;
    }

    /**
     * Retorna se deve usar o cache
     * @return boolean
     */
    public function getUseCache()
    {
        return $this->useCache;
    }

    /**
     * Define se deve usar o cache
     * @param boolean $useCache
     * @return ServiceAbstract
     */
    public function setUseCache($useCache)
    {
        $this->useCache = $useCache;
        $this->getMapper()->setUseCache($useCache);
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getHtmlSelectOption()
    {
        return $this->htmlSelectOption;
    }

    /**
     *
     * @param string $htmlSelectOption
     *
     * @return self
     */
    public function setHtmlSelectOption($htmlSelectOption)
    {
        $this->htmlSelectOption = $htmlSelectOption;
        return $this;
    }

    /**
     *
     * @return array|string
     */
    public function getHtmlSelectOptionData()
    {
        return $this->htmlSelectOptionData;
    }

    /**
     *
     * @param array|string $htmlSelectOptionData
     *
     * @return self
     */
    public function setHtmlSelectOptionData($htmlSelectOptionData)
    {
        $this->htmlSelectOptionData = $htmlSelectOptionData;
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
            if (empty($this->getMapper()->getTableKey())) {
                throw new InvalidArgumentException('Chave não definida em ' . get_class($this));
            }

            // Verifica se é uma chave múltipla ou com cast
            if (is_array($this->getMapper()->getTableKey())) {
                // Verifica se é uma chave simples com cast
                if (count($this->getMapper()->getTableKey()) !== 1) {
                    throw new InvalidArgumentException('Não é possível acessar chaves múltiplas informando apenas uma');
                }
                $where = [$this->getMapper()->getTableKey(true) => $where];
            } else {
                $where = [$this->getMapper()->getTableKey() => $where];
            }
        }

        // Cria a assinatura da consulta
        $cacheKey = 'findOne'
            . $this->getUniqueCacheKey()
            . md5(
                $this->getMapper()->getSelect($where, $order)->getSqlString(
                    $this->getMapper()->getTableGateway()->getAdapter()->getPlatform()
                )
            );

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $findOne = $this->getMapper()->fetchRow($where, $order);

        // Grava a consulta no cache
        if ($this->getUseCache()) {
            $this->getCache()->setItem($cacheKey, $findOne);
        }

        return $findOne;
    }

    /**
     * Consultas especiais do service
     *
     * @param array $where
     * @return array
     */
    public function getWhere($where)
    {
        return $where;
    }

    /**
     * CONTROLE DE CACHE
     */

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
    public function findAssoc($where = null, $order = null, $count = null, $offset = null)
    {
        // Cria a assinatura da consulta
        $cacheKey = 'findAssoc'
            . $this->getUniqueCacheKey()
            . '_key' . $this->getMapper()->getTableKey(true) . '_'
            . md5(
                $this->getMapper()->getSelect($this->getWhere($where), $order, $count, $offset)->getSqlString(
                    $this->getMapper()->getTableGateway()->getAdapter()->getPlatform()
                )
            );

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $fetchAll = $this->getMapper()->fetchAll($this->getWhere($where), $order, $count, $offset);
        $findAssoc = [];
        if (!empty($fetchAll)) {
            foreach ($fetchAll as $row) {
                $findAssoc[$row[$this->getMapper()->getTableKey(true)]] = $row;
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
     * @return Paginator
     */
    public function findPaginated($where = null, $order = null, $count = null, $offset = null)
    {
        // Define a consulta
        if ($where instanceof Select) {
            $select = $where;
        } else {
            $select = $this->getMapper()->getSelect($this->getWhere($where), $order, $count, $offset);
        }

        // Verifica se deve usar o cache
        $cacheKey = 'findPaginated'
            . $this->getUniqueCacheKey()
            . md5($select->getSqlString($this->getMapper()->getTableGateway()->getAdapter()->getPlatform()));

        // Verifica se tem no cache
        if ($this->getUseCache() && $this->getCache()->hasItem($cacheKey)) {
            return $this->getCache()->getItem($cacheKey);
        }

        $resultSet = new HydratingResultSet($this->getMapper()->getHydrator(), $this->getMapper()->getHydratorEntity());
        $adapter = new DbSelect($select, $this->getMapper()->getTableGateway()->getAdapter(), $resultSet);

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
        return $this->getMapper()->insert($set);
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
        return $this->getMapper()->update($set, $key);
    }

    /**
     * @return boolean
     */
    public function getUseJoin()
    {
        return $this->getMapper()->getUseJoin();
    }

    /**
     * @param boolean $useJoin
     * @return ServiceAbstract
     */
    public function setUseJoin($useJoin)
    {
        $this->getMapper()->setUseJoin($useJoin);
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
        $this->getMapper()->getCache()->flush();
    }

    /**
     * @return boolean
     */
    public function getAutoCleanCache()
    {
        return $this->getMapper()->getAutoCleanCache();
    }

    /**
     * @param boolean $autoCleanCache
     *
     * @return ServiceAbstract
     */
    public function setAutoCleanCache($autoCleanCache)
    {
        $this->getMapper()->setAutoCleanCache($autoCleanCache);

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
}
