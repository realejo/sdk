<?php

namespace RealejoTest\Sdk\Test;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;

trait DbTrait
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * Created tables list to dro after execution
     *
     * @var array
     */
    protected $createdTables = [];

    protected $createTableSources = [
        __DIR__ . '/../../assets/sql',
    ];

    /**
     * @var TableGateway[]
     */
    private $tableGateway;

    /**
     * @return Adapter
     */
    public function getAdapter(): Adapter
    {
        if (null === $this->adapter) {
            if (isset($this->application)) {
                $this->adapter = $this->getApplicationServiceLocator()->get(Adapter::class);
            } else {
                $configPath = realpath(__DIR__ . '/../../config');
                if ($configPath === false) {
                    $this->fail('Pasta de /config não localizada');
                }

                if (file_exists($configPath . '/db.php')) {
                    $dbConfig = require $configPath . '/db.php';
                } else {
                    $dbConfig = require $configPath . '/db.php.dist';
                }

                $this->adapter = new Adapter($dbConfig);
            }
        }
        return $this->adapter;
    }

    public function setAdapter(Adapter $adapter): void
    {
        $this->adapter = $adapter;
    }

    public function createTables(array $tables = null): void
    {
        foreach ($tables as $tbl) {
            $filePath = $this->findCreateScript($tbl);
            $this->getAdapter()->query(file_get_contents($filePath), Adapter::QUERY_MODE_EXECUTE);
            $this->createdTables[] = $tbl;
        }
    }

    private function findCreateScript(string $table): string
    {
        foreach ($this->createTableSources as $path) {
            $filePath = realpath($path) . "/{$table}.sql";
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        $this->fail("No SQL create script fot table '$table' found");
    }

    public function dropTables(array $tables = null): void
    {
        if ($tables === null) {
            $tables = array_reverse($this->createdTables);
        }

        if (!empty($tables)) {
            // Desabilita os indices e constrains para não dar erro
            // ao apagar uma tabela com foreign key
            // No mundo real isso é inviável, mas nos teste podemos
            // ignorar as foreign keys APÓS os testes
            $this->getAdapter()->query('SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;');
            $this->getAdapter()->query('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;');
            $this->getAdapter()->query('SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=\'TRADITIONAL,ALLOW_INVALID_DATES\';');

            // Recupera o script para remover as tabelas
            foreach ($tables as $tbl) {
                $this->getAdapter()->query("DROP TABLE IF EXISTS `$tbl`", Adapter::QUERY_MODE_EXECUTE);
                $this->createdTables = array_diff($this->createdTables, [$tbl]);
            }

            $this->getAdapter()->query('SET SQL_MODE=@OLD_SQL_MODE;');
            $this->getAdapter()->query('SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;');
            $this->getAdapter()->query('SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;');
        }
    }

    public function dbExecute(string $sql)
    {
        return $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }

    public function dbFetchAll(string $sql): array
    {
        return $this->dbExecute($sql)->toArray();
    }

    public function dbUpdate(string $table, $set, $key = null): int
    {
        if (!isset($this->tableGateway[$table])) {
            $this->tableGateway[$table] = new TableGateway($table, $this->getAdapter());
        }
        return $this->tableGateway[$table]->update($set, $key);
    }

    public function dbFetchOne(string $sql): ?array
    {
        $fetchAll = $this->dbFetchAll($sql);
        if (count($fetchAll) > 0) {
            return array_shift($fetchAll);
        }
        return null;
    }

    public function dbFetchCount(string $table, string $where = null): int
    {
        if ($where !== null) {
            $where = "WHERE $where";
        }
        $fetchCount = $this->dbFetchOne("SELECT count(*) as `c` from $table $where");
        return $fetchCount['c'];
    }

    /**
     * @param string|TableGateway $table
     * @param array $rows
     */
    public function insertRows($table, array $rows): void
    {
        if (is_string($table)) {
            $table = new TableGateway($table, $this->getAdapter());
        } elseif (!$table instanceof TableGateway) {
            throw new \InvalidArgumentException("$table deve ser uma string ou TableGateway");
        }

        foreach ($rows as $r) {
            $table->insert($r);
        }
    }

    public function getCreatedTables(): array
    {
        return $this->createdTables;
    }
}
