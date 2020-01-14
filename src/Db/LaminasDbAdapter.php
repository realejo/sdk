<?php

namespace Realejo\Sdk\Db;

use InvalidArgumentException;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\ArraySerializable;
use LogicException;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;

class LaminasDbAdapter implements AdapterInterface
{
    public const KEY_STRING = 'STRING';
    public const KEY_INTEGER = 'INTEGER';

    /**
     * @var stdClass
     */
    protected $hydratorEntity;

    /**
     * @var ArraySerializable
     */
    protected $hydrator;

    /**
     * @var bool
     */
    protected $useHydrateResultSet = false;

    /**
     * Nome da tabela a ser usada
     * @var string
     */
    protected $tableName;

    /**
     * @var TableGateway
     */
    protected $tableGateway;

    /**
     * Define o nome da chave
     * @var string|array
     */
    protected $tableKey;

    /**
     * Join lefts que devem ser usados no mapper
     *
     * @var array
     */
    protected $tableJoin;

    /**
     * Join lefts que devem ser usados no mapper
     *
     * @var bool
     */
    protected $useJoin = false;

    /**
     * Define se deve usar todas as chaves para os operações de update e delete
     *
     * @var bool
     */
    protected $useAllKeys = true;

    /**
     * Define a ordem padrão a ser usada na consultas
     *
     * @var array
     */
    protected $defaultOrder = [];

    /**
     * Define o adapter a ser usado
     *
     * @var Adapter
     */
    protected $adapter;

    /**
     * Define se deve remover os registros ou apenas marcar como removido
     *
     * @var bool
     */
    protected $useDeleted = false;

    /**
     * Define se deve mostrar os registros marcados como removido
     *
     * @var bool
     */
    protected $showDeleted = false;

    /**
     * @var ContainerInterface
     */
    protected $serviceLocator;

    protected $lastInsertSet;
    protected $lastInsertKey;
    protected $lastUpdateSet;
    protected $lastUpdateDiff;
    protected $lastUpdateKey;
    protected $lastDeleteKey;

    /**
     *
     * @param string $tableName Nome da tabela a ser usada
     * @param string|array $tableKey Nome ou array de chaves a serem usadas
     *
     */
    public function __construct(string $tableName = null, $tableKey = null)
    {
        if (!empty($tableName)) {
            $this->tableName = $tableName;
        }

        if (!empty($tableKey) && (is_string($tableKey) || is_array($tableKey))) {
            $this->tableKey = $tableKey;
        }
    }

    /**
     * Excluí um registro
     *
     * @param int|array $key Código da registro a ser excluído
     *
     * @return bool Informa se teve o registro foi removido
     */
    public function delete($key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException("Chave <b>'$key'</b> inválida");
        }

        if (!is_array($key) && is_array($this->getTableKey()) && count($this->getTableKey()) > 1) {
            throw new InvalidArgumentException('Não é possível apagar um registro usando chaves múltiplas parciais');
        }

        // Grava os dados alterados para referencia
        $this->lastDeleteKey = $key;

        // Verifica se deve marcar como removido ou remover o registro
        if ($this->useDeleted === true) {
            $return = $this->getTableGateway()->update(['deleted' => 1], $this->getKeyWhere($key));
        } else {
            $return = $this->getTableGateway()->delete($this->getKeyWhere($key));
        }

        // Retorna se o registro foi excluído
        return $return;
    }

    /**
     * Retorna a chave definida para a tabela
     *
     * @param bool $returnSingle Quando for uma chave multipla, use TRUE para retorna a primeira chave
     * @return array|string
     */
    public function getTableKey(bool $returnSingle = false)
    {
        $key = $this->tableKey;

        // Verifica se é para retorna apenas a primeira da chave multipla
        if (is_array($key) && $returnSingle === true) {
            if (is_array($key)) {
                foreach ($key as $type => $keyName) {
                    $key = $keyName;
                    break;
                }
            }
        }

        return $key;
    }

    public function setTableKey($key): self
    {
        if (empty($key) && !is_string($key) && !is_array($key)) {
            throw new InvalidArgumentException('Chave inválida em ' . get_class($this));
        }

        $this->tableKey = $key;

        return $this;
    }

    public function getTableGateway(): TableGateway
    {
        if (null === $this->tableName) {
            throw new InvalidArgumentException('Tabela não definida em ' . get_class($this));
        }

        // Verifica se a tabela já foi previamente carregada
        if (null === $this->tableGateway) {
            $this->tableGateway = new TableGateway($this->tableName, $this->getAdapter());
        }

        // Retorna a tabela
        return $this->tableGateway;
    }

    public function getAdapter(): Adapter
    {
        if (null === $this->adapter) {
            if ($this->hasServiceLocator() && $this->getServiceLocator()->has(Adapter::class)) {
                $this->adapter = $this->getServiceLocator()->get(Adapter::class);
                return $this->adapter;
            }

            throw new RuntimeException('Adapter não definido');
        }

        return $this->adapter;
    }

    public function setAdapter(Adapter $adapter): self
    {
        $this->adapter = $adapter;
        return $this;
    }

    public function hasServiceLocator(): bool
    {
        return null !== $this->serviceLocator;
    }

    public function getServiceLocator(): ContainerInterface
    {
        return $this->serviceLocator;
    }

    public function setServiceLocator(ContainerInterface $serviceLocator): self
    {
        $this->serviceLocator = $serviceLocator;

        return $this;
    }

    /**
     * Retorna a chave no formato que ela deve ser usada
     *
     * @param Expression|string|array $key
     *
     * @return Expression|string
     */
    protected function getKeyWhere($key)
    {
        if ($key instanceof Expression) {
            return $key;
        }

        if (is_numeric($key) && is_string($this->getTableKey())) {
            return "{$this->getTableKey()} = $key";
        }

        if (is_string($key) && is_string($this->getTableKey())) {
            return "{$this->getTableKey()} = '$key'";
        }

        if (!is_array($this->getTableKey())) {
            throw new LogicException('Chave mal definida em ' . get_class($this));
        }

        $where = [];
        $usedKeys = [];

        // Verifica as chaves definidas
        foreach ($this->getTableKey() as $type => $definedKey) {
            // Verifica se é uma chave única com cast
            if (!is_array($key) && count($this->getTableKey()) === 1) {
                // Grava a chave como integer
                if (is_numeric($type) || $type === self::KEY_INTEGER) {
                    $where[] = "$definedKey = $key";
                    // Grava a chave como string
                } elseif ($type === self::KEY_STRING) {
                    $where[] = "$definedKey = '$key'";
                }

                $usedKeys[] = $definedKey;
            } // Verifica se a chave definida foi informada
            elseif (is_array($key) && !is_array($definedKey) && isset($key[$definedKey])) {
                // Grava a chave como integer
                if (is_numeric($type) || $type === self::KEY_INTEGER) {
                    $where[] = "$definedKey = {$key[$definedKey]}";
                    // Grava a chave como string
                } elseif ($type === self::KEY_STRING) {
                    $where[] = "$definedKey = '{$key[$definedKey]}'";
                }

                // Marca a chave com usada
                $usedKeys[] = $definedKey;
            } elseif (is_array($key) && is_array($definedKey)) {
                foreach ($definedKey as $value) {
                    // Grava a chave como integer
                    if (is_numeric($value) || $type === self::KEY_INTEGER) {
                        $where[] = "$value = {$key[$value]}";
                        // Grava a chave como string
                    } elseif ($type === self::KEY_STRING) {
                        $where[] = "$value = '{$key[$value]}'";
                    }
                }

                // Marca a chave com usada
                $usedKeys[] = $definedKey;
            }
        }

        // Verifica se alguma chave foi definida
        if (empty($where)) {
            throw new LogicException('Nenhuma chave definida em ' . get_class($this));
        }

        // Verifica se todas as chaves foram usadas
        if (
            $this->getUseAllKeys() === true
            && is_array($this->getTableKey())
            && count($usedKeys) !== count($this->getTableKey())
        ) {
            throw new LogicException('Não é permitido usar chaves parciais em ' . get_class($this));
        }

        return '(' . implode(') AND (', $where) . ')';
    }

    public function getUseAllKeys(): bool
    {
        return $this->useAllKeys;
    }

    public function setUseAllKeys(bool $useAllKeys): self
    {
        $this->useAllKeys = $useAllKeys;

        return $this;
    }

    /**
     * @param array $set
     * @return int|array
     */
    public function save(array $set)
    {
        if (!isset($set[$this->getTableKey()])) {
            return $this->insert($set);
        }

        // Caso não seja, envia um Exception
        if (!is_numeric($set[$this->getTableKey()])) {
            throw new RuntimeException("Chave invalida: '{$set[$this->getTableKey()]}'");
        }

        if ($this->fetchRow($set[$this->getTableKey()])) {
            return $this->update($set, $set[$this->getTableKey()]);
        }

        throw new RuntimeException("{$this->getTableKey()} key does not exist in " . get_class($this));
    }

    /**
     * Grava um novo registro
     *
     * @param array $set
     * @return int|array boolean
     *
     */
    public function insert(array $set)
    {
        // Verifica se há algo a ser adicionado
        if (empty($set)) {
            return false;
        }

        // Grava o ultimo set incluído para referencia
        $this->lastInsertSet = $set;
        // Cria um objeto para conseguir usar o hydrator
        if (is_array($set)) {
            $set = new stdClass($set);
        }

        $hydrator = $this->getHydrator();
        $set = $hydrator->extract($set);

        // Remove os campos vazios
        foreach ($set as $field => $value) {
            if (is_string($value)) {
                $set[$field] = trim($value);
                if ($set[$field] === '') {
                    $set[$field] = null;
                }
            }
        }

        // Grava o set no BD
        $this->getTableGateway()->insert($set);

        // Recupera a chave gerada do registro
        if (is_array($this->getTableKey())) {
            $rightKeys = $this->getTableKey();
            $key = [];
            foreach ($rightKeys as $type => $k) {
                if (is_array($k)) {
                    foreach ($k as $value) {
                        // Grava a chave como integer
                        if (is_numeric($value) || $type === self::KEY_INTEGER) {
                            $key[$value] = $set[$value];
                            // Grava a chave como string
                        } elseif ($type === self::KEY_STRING) {
                            $key[$value] = $set[$value];
                        }
                    }
                } else {
                    if (isset($set[$k])) {
                        $key[$k] = $set[$k];
                    } else {
                        $key = false;
                        break;
                    }
                }
            }
        } elseif (isset($set[$this->getTableKey()])) {
            $key = $set[$this->getTableKey()];
        }

        if (empty($key)) {
            $key = $this->getTableGateway()->getAdapter()->getDriver()->getLastGeneratedValue();
        }

        // Grava a chave criada para referencia
        $this->lastInsertKey = $key;

        // Retorna o código do registro recem criado
        return $key;
    }

    /**
     * @return ArraySerializable
     */
    public function getHydrator()
    {
        return new ArraySerializable();
    }

    /**
     * @param ArraySerializable $hydrator
     *
     * @return self
     */
    public function setHydrator(ArraySerializable $hydrator)
    {
        if (empty($hydrator)) {
            throw new InvalidArgumentException('Invalid hydrator');
        }
        $this->hydrator = $hydrator;

        return $this;
    }

    /**
     * Recupera um registro
     *
     * @param mixed $where condições para localizar o registro
     * @param string|array $order
     *
     * @return null|stdClass
     */
    public function fetchRow($where, $order = null)
    {
        // Define se é a chave da tabela
        if (is_numeric($where) || is_string($where)) {
            // Verifica se há chave definida
            if (empty($this->tableKey)) {
                throw new InvalidArgumentException('Chave não definida em ' . get_class($this));
            }

            // Verifica se é uma chave múltipla ou com cast
            if (is_array($this->tableKey)) {
                // Verifica se é uma chave simples com cast
                if (count($this->tableKey) != 1) {
                    throw new InvalidArgumentException('Não é possível acessar chaves múltiplas informando apenas uma');
                }
                $where = [$this->getTableKey(true) => $where];
            } else {
                $where = [$this->tableKey => $where];
            }
        }

        // Recupera o registro
        $fetchRow = $this->fetchAll($where, $order, 1);

        if ($this->useHydrateResultSet) {
            return !empty($fetchRow) ? $fetchRow->current() : null;
        }

        return !empty($fetchRow) ? $fetchRow[0] : null;
    }

    /**
     * @param array $where condições para localizar o registro
     * @param array|string $order
     * @param int $count
     * @param int $offset
     *
     * @return stdClass[]|HydratingResultSet
     */
    public function fetchAll(array $where = null, array $order = null, int $count = null, int $offset = null): ?array
    {
        $where = $where ?: [];
        $order = $order ?: [];
        return $this->findAllWithSelect($this->getSelect($where, $order, $count, $offset));
    }

    public function findAllWithSelect(Select $select)
    {
        // Recupera os registros do banco de dados
        $fetchAll = $this->getTableGateway()->selectWith($select);

        // Verifica se foi localizado algum registro
        if ($fetchAll !== null && count($fetchAll) > 0) {
            // Passa o $fetch para array para poder incluir campos extras
            $fetchAll = $fetchAll->toArray();
        } else {
            $fetchAll = null;
        }

        if (empty($fetchAll)) {
            return $fetchAll;
        }

        $hydrator = $this->getHydrator();
        if ($hydrator === null) {
            return $fetchAll;
        }
        $hydratorEntity = $this->getHydratorEntity();

        if ($this->useHydrateResultSet) {
            $hydrateResultSet = new HydratingResultSet($hydrator, new $hydratorEntity());
            $hydrateResultSet->initialize(new ArrayIterator($fetchAll));

            return $hydrateResultSet;
        }

        foreach ($fetchAll as $id => $row) {
            $fetchAll[$id] = $hydrator->hydrate($row, new $hydratorEntity());
        }

        return $fetchAll;
    }

    /**
     * Retorna o select para a consulta
     *
     * @param array $where OPTIONAL An SQL WHERE clause
     * @param array $order OPTIONAL An SQL ORDER clause.
     * @param int $count OPTIONAL An SQL LIMIT count.
     * @param int $offset OPTIONAL An SQL LIMIT offset.
     *
     * @return Select
     */
    public function getSelect(array $where = [], array $order = [], int $count = null, int $offset = null): Select
    {
        // Retorna o select para a tabela
        $select = $this->getTableSelect();

        // Define a ordem
        $select->order($order ?: $this->defaultOrder);

        // Verifica se há paginação, não confundir com o Zend\Paginator
        if ($count !== null) {
            $select->limit($count);
        }
        if ($offset !== null) {
            $select->offset($offset);
        }

        // Checks $where is deleted
        if (!isset($where['deleted']) && $this->getUseDeleted() && !$this->getShowDeleted()) {
            $where['deleted'] = 0;
        }

        // processa as clausulas
        foreach ($where as $id => $w) {
            if (is_numeric($id) && $w instanceof Expression) {
                if (!$w instanceof Predicate\Expression) {
                    $select->where(new Predicate\Expression($w->getExpression()));
                } else {
                    $select->where($w);
                }
                continue;
            }

            // Checks is deleted
            if ($id === 'deleted' && $w === false) {
                $select->where("{$this->getTableGateway()->getTable()}.deleted=0");
                continue;
            }

            if ($id === 'deleted' && $w === true) {
                $select->where("{$this->getTableGateway()->getTable()}.deleted=1");
                continue;
            }

            // Valor numerico
            if (!is_numeric($id) && is_numeric($w)) {
                if (strpos($id, '.') === false) {
                    $id = "{$this->tableName}.$id";
                }
                $select->where(new Predicate\Operator($id, '=', $w));
                continue;
            }

            // Texto e Data
            if (!is_numeric($id)) {
                if (strpos($id, '.') === false) {
                    $id = "{$this->tableName}.$id";
                }

                if ($w === null) {
                    $select->where(new Predicate\IsNull($id));
                } else {
                    $select->where(new Predicate\Operator($id, '=', $w));
                }
                continue;
            }

            throw new LogicException("Condição inválida '$w' em " . get_class($this));
        }

        return $select;
    }

    /**
     * Retorna o select a ser usado no fetchAll e fetchRow
     */
    public function getTableSelect(): Select
    {
        $select = $this->getTableGateway()->getSql()->select();

        if (!empty($this->tableJoin) && $this->getUseJoin()) {
            foreach ($this->tableJoin as $definition) {
                if (empty($definition['table']) && !is_string($definition['table'])) {
                    throw new InvalidArgumentException('Tabela não definida em ' . get_class($this));
                }

                if (empty($definition['condition']) && !is_string($definition['condition'])) {
                    throw new InvalidArgumentException(
                        "Condição para a tabela {$definition['table']} não definida em " . get_class($this)
                    );
                }

                if (
                    isset($definition['columns']) && !empty($definition['columns'])
                    && !is_array($definition['columns'])
                ) {
                    throw new InvalidArgumentException(
                        "Colunas para a tabela {$definition['table']} devem ser um array em " . get_class($this)
                    );
                }

                if (array_key_exists('schema', $definition)) {
                    trigger_error('Schema não pode ser usado. Use type.', E_USER_DEPRECATED);
                    $definition['type'] = $definition['schema'];
                }

                if (isset($definition['type']) && !empty($definition['type']) && !is_string($definition['type'])) {
                    throw new InvalidArgumentException('Type devem ser uma string em ' . get_class($this));
                }

                if (!isset($definition['type'])) {
                    $definition['type'] = null;
                }

                $select->join(
                    $definition['table'],
                    $definition['condition'],
                    $definition['columns'],
                    $definition['type']
                );
            }
        }

        return $select;
    }

    /**
     * @return bool
     */
    public function getUseJoin()
    {
        return $this->useJoin;
    }

    /**
     * @param bool $useJoin
     *
     * @return self
     */
    public function setUseJoin(bool $useJoin)
    {
        $this->useJoin = $useJoin;
        return $this;
    }

    /**
     * Retorna se irá usar o campo deleted ou remover o registro quando usar delete()
     *
     * @return bool
     */
    public function getUseDeleted(): bool
    {
        return $this->useDeleted;
    }

    /**
     * Define se irá usar o campo deleted ou remover o registro quando usar delete()
     *
     * @param bool $useDeleted
     *
     * @return self
     */
    public function setUseDeleted(bool $useDeleted): LaminasDbAdapter
    {
        $this->useDeleted = $useDeleted;

        return $this;
    }

    /**
     * Retorna se deve retornar os registros marcados como removidos
     *
     * @return bool
     */
    public function getShowDeleted(): bool
    {
        return $this->showDeleted;
    }

    /**
     * Define se deve retornar os registros marcados como removidos
     *
     * @param bool $showDeleted
     *
     * @return self
     */
    public function setShowDeleted(bool $showDeleted): LaminasDbAdapter
    {
        $this->showDeleted = $showDeleted;

        return $this;
    }

    /**
     * @param bool $asObject
     * @return stdClass|string
     */
    public function getHydratorEntity($asObject = true)
    {
        if ($asObject === false) {
            return $this->hydratorEntity;
        }

        if (isset($this->hydratorEntity)) {
            $hydrator = $this->hydratorEntity;
            return new $hydrator();
        }

        return new stdClass();
    }

    /**
     * @param string $hydratorEntity
     *
     * @return self
     */
    public function setHydratorEntity(string $hydratorEntity): LaminasDbAdapter
    {
        $this->hydratorEntity = $hydratorEntity;

        return $this;
    }

    /**
     * Altera um registro
     *
     * @param array $set Dados a serem atualizados
     * @param int|array $key Chave do registro a ser alterado
     *
     * @return int
     */
    public function update(array $set, $key)
    {
        // Verifica se o código é válido
        if (empty($key)) {
            throw new InvalidArgumentException("Chave <b>'$key'</b> inválida");
        }

        // Verifica se há algo para alterar
        if (empty($set)) {
            return false;
        }

        // Cria um objeto para conseguir usar o hydrator
        if (is_array($set)) {
            $set = new stdClass($set);
        }

        // Recupera os dados existentes
        $row = $this->fetchRow($key);

        // Verifica se existe o registro
        if (empty($row)) {
            return false;
        }

        $hydrator = $this->getHydrator();
        $row = $hydrator->extract($row);
        $set = $hydrator->extract($set);

        //@todo Quem deveria fazer isso é o hydrator!
        /* if ($row instanceof Metadata\stdClass) {
             $row = $row->toArray();
             if (isset($row['metadata'])) {
                 $row[$row->getMappedKeyname('metadata', true)] = $row['metadata'];
                 unset($row['metadata']);
             }
         }*/

        // Remove os campos vazios
        foreach ($set as $field => $value) {
            if (is_string($value)) {
                $set[$field] = trim($value);
                if ($set[$field] === '') {
                    $set[$field] = null;
                }
            }
        }

        // Verifica se há o que atualizar
        $diff = $this->array_diff_assoc_recursive($set, $row);

        // Grava os dados alterados para referencia
        $this->lastUpdateSet = $set;
        $this->lastUpdateKey = $key;

        // Grava o que foi alterado
        $this->lastUpdateDiff = [];
        foreach ($diff as $field => $value) {
            $this->lastUpdateDiff[$field] = [$row[$field], $value];
        }

        // Verifica se há algo para atualizar
        if (empty($diff)) {
            return false;
        }

        // Salva os dados alterados e retorna que o registro foi alterado
        return $this->getTableGateway()->update($diff, $this->getKeyWhere($key));
    }

    private function array_diff_assoc_recursive($array1, $array2): array
    {
        $difference = [];
        foreach ($array1 as $key => $value) {
            if (!is_array($value)) {
                if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                    $difference[$key] = $value;
                }
            } elseif (!isset($array2[$key]) || !is_array($array2[$key])) {
                $difference[$key] = $value;
            } else {
                $new_diff = self::array_diff_assoc_recursive($value, $array2[$key]);
                if (!empty($new_diff)) {
                    $difference[$key] = $new_diff;
                }
            }
        }
        return $difference;
    }

    public function getLastInsertSet(): ?array
    {
        return $this->lastInsertSet;
    }

    public function getLastInsertKey(): ?array
    {
        return $this->lastInsertKey;
    }

    public function getLastUpdateSet(): ?array
    {
        return $this->lastUpdateSet;
    }

    public function getLastUpdateDiff(): ?array
    {
        return $this->lastUpdateDiff;
    }

    public function getLastUpdateKey(): ?array
    {
        return $this->lastUpdateKey;
    }

    public function getLastDeleteKey(): ?array
    {
        return $this->lastDeleteKey;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function getDefaultOrder(): ?array
    {
        return $this->defaultOrder;
    }

    public function setDefaultOrder(array $order): LaminasDbAdapter
    {
        $this->defaultOrder = $order;
        return $this;
    }

    public function isUseHydrateResultSet(): bool
    {
        return $this->useHydrateResultSet;
    }

    public function setUseHydrateResultSet(bool $useHydrateResultSet): LaminasDbAdapter
    {
        $this->useHydrateResultSet = $useHydrateResultSet;
        return $this;
    }

    public function getTableJoin(): array
    {
        return $this->tableJoin;
    }

    public function setTableJoin(array $tableJoin): self
    {
        $this->tableJoin = $tableJoin;
        return $this;
    }
}
