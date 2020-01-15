<?php

namespace RealejoTest\Sdk\Db;

use InvalidArgumentException;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayObject;
use LogicException;
use PHPUnit\Framework\TestCase;
use Realejo\Sdk\Db\LaminasDbAdapter;
use RealejoTest\Sdk\Test\DbTrait;

class LaminasDbAdapterTest extends TestCase
{
    use DbTrait;

    /**
     * @var string
     */
    protected $tableName = 'album';

    /**
     * @var string
     */
    protected $tableKeyName = 'id';

    protected $tables = ['album'];

    /**
     * @var LaminasDbAdapter
     */
    private $laminasDbAdapter;

    protected $defaultValues = [
        [
            'id' => 1,
            'artist' => 'Rush',
            'title' => 'Rush',
            'deleted' => 0
        ],
        [
            'id' => 2,
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => 0
        ],
        [
            'id' => 3,
            'artist' => 'Dream Theater',
            'title' => 'Images And Words',
            'deleted' => 0
        ],
        [
            'id' => 4,
            'artist' => 'Claudia Leitte',
            'title' => 'Exttravasa',
            'deleted' => 1
        ]
    ];

    public function insertDefaultRows(): void
    {
        foreach ($this->defaultValues as $row) {
            $this->getAdapter()->query(
                "INSERT into {$this->tableName}({$this->tableKeyName}, artist, title, deleted)
                                        VALUES (
                                            {$row[$this->tableKeyName]},
                                            '{$row['artist']}',
                                            '{$row['title']}',
                                            {$row['deleted']}
                                        );",
                Adapter::QUERY_MODE_EXECUTE
            );
        }
    }

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTables($this->tables);
        $this->createTables($this->tables);
        $this->insertDefaultRows();

        // Configura o mapper
        $this->laminasDbAdapter = new LaminasDbAdapter($this->tableName, $this->tableKeyName);
        $this->laminasDbAdapter->setAdapter($this->getAdapter());
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->dropTables();
    }

    /**
     * Definição de chave invalido
     * @expectedException InvalidArgumentException
     */
    public function testKeyNameInvalido(): void
    {
        $this->laminasDbAdapter->setTableKey(null);
    }

    /**
     * Definição de ordem invalido
     * @expectedException InvalidArgumentException
     */
    public function testFetchRowMultiKeyException(): void
    {
        // Cria a tabela com chave string
        $this->laminasDbAdapter->setTableKey(
            [LaminasDbAdapter::KEY_INTEGER => 'id_int', LaminasDbAdapter::KEY_STRING => 'id_char']
        );
        $this->laminasDbAdapter->fetchRow(1);
    }

    public function testTableKeyGettersSetters(): void
    {
        $this->assertEquals('meuid', $this->laminasDbAdapter->setTableKey('meuid')->getTableKey());
        $this->assertEquals('meuid', $this->laminasDbAdapter->setTableKey('meuid')->getTableKey(true));
        $this->assertEquals('meuid', $this->laminasDbAdapter->setTableKey('meuid')->getTableKey(false));

        $this->assertEquals(
            ['meuid' => LaminasDbAdapter::KEY_INTEGER, 'com array' => LaminasDbAdapter::KEY_INTEGER],
            $this->laminasDbAdapter->setTableKey(['meuid', 'com array'])->getTableKey()
        );
        $this->assertEquals(
            ['meuid' => LaminasDbAdapter::KEY_INTEGER, 'com array' => LaminasDbAdapter::KEY_INTEGER],
            $this->laminasDbAdapter->setTableKey(['meuid', 'com array'])->getTableKey(false)
        );
        $this->assertEquals(
            'meuid',
            $this->laminasDbAdapter->setTableKey(['meuid', 'com array'])->getTableKey(true)
        );
    }

    public function testTableKeyGettersSettersInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid key definition');
        $this->laminasDbAdapter->setTableKey(new Sql\Expression('chave muito exotica!'))->getTableKey();
    }

    public function testTableKeyGettersSettersInvalidComplexKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid key definition');
        $this->laminasDbAdapter->setTableKey(
            [
                new Sql\Expression('chave muito mais exotica!'),
            ]
        );
    }

    public function testGettersSetters(): void
    {
        $this->assertEquals(
            ['minhaordem'],
            $this->laminasDbAdapter->setDefaultOrder(['minhaordem'])->getDefaultOrder()
        );
        $this->assertEquals(
            ['minhaordem', 'comarray'],
            $this->laminasDbAdapter->setDefaultOrder(['minhaordem', 'comarray'])->getDefaultOrder()
        );
        $this->assertInstanceOf(
            Expression::class,
            $this->laminasDbAdapter->setDefaultOrder([new Sql\Expression('ordem muito exotica!')])->getDefaultOrder()[0]
        );
    }

    public function testConstructAndServiceManager(): void
    {
        $LaminasDbAdapter = new LaminasDbAdapter($this->tableName, $this->tableKeyName);
        $this->assertEquals($this->tableKeyName, $LaminasDbAdapter->getTableKey());
        $this->assertEquals($this->tableName, $LaminasDbAdapter->getTableName());

        $LaminasDbAdapter = new LaminasDbAdapter($this->tableName, [$this->tableKeyName, $this->tableKeyName]);
        $this->assertEquals([$this->tableKeyName, $this->tableKeyName], $LaminasDbAdapter->getTableKey());
        $this->assertEquals($this->tableName, $LaminasDbAdapter->getTableName());

        $LaminasDbAdapter = new LaminasDbAdapter($this->tableName, $this->tableKeyName);
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            Adapter::class,
            $this->getAdapter()
        );
        $LaminasDbAdapter->setServiceLocator($serviceManager);

        $this->assertInstanceOf(
            get_class($this->getAdapter()),
            $LaminasDbAdapter->getTableGateway()->getAdapter(),
            'tem o Adapter padrão'
        );
        $this->assertEquals(
            $this->getAdapter(),
            $LaminasDbAdapter->getTableGateway()->getAdapter(),
            'tem a mesma configuração do adapter padrão'
        );
    }

    /**
     * Tests Base->getDefaultOrder()
     */
    public function testOrder(): void
    {
        // Verifica a ordem padrão
        $this->assertEquals([], $this->laminasDbAdapter->getDefaultOrder());

        // Define uma nova ordem com string
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $this->assertEquals(['id'], $this->laminasDbAdapter->getDefaultOrder());

        // Define uma nova ordem com string
        $this->laminasDbAdapter->setDefaultOrder(['title']);
        $this->assertEquals(['title'], $this->laminasDbAdapter->getDefaultOrder());

        // Define uma nova ordem com array
        $this->laminasDbAdapter->setDefaultOrder(['id', 'title']);
        $this->assertEquals(['id', 'title'], $this->laminasDbAdapter->getDefaultOrder());
    }

    /**
     * Tests campo deleted
     */
    public function testDeletedField(): void
    {
        // Verifica se deve remover o registro
        $this->laminasDbAdapter->setUseDeleted(false);
        $this->assertFalse($this->laminasDbAdapter->getUseDeleted());
        $this->assertTrue($this->laminasDbAdapter->setUseDeleted(true)->getUseDeleted());
        $this->assertFalse($this->laminasDbAdapter->setUseDeleted(false)->getUseDeleted());
        $this->assertFalse($this->laminasDbAdapter->getUseDeleted());

        // Verifica se deve mostrar o registro
        $this->laminasDbAdapter->setShowDeleted(false);
        $this->assertFalse($this->laminasDbAdapter->getShowDeleted());
        $this->assertFalse($this->laminasDbAdapter->setShowDeleted(false)->getShowDeleted());
        $this->assertTrue($this->laminasDbAdapter->setShowDeleted(true)->getShowDeleted());
        $this->assertTrue($this->laminasDbAdapter->getShowDeleted());
    }

    /**
     * Tests Base->getSQlString()
     */
    public function testGetSQlString(): void
    {
        // Verifica o padrão de não usar o campo deleted e não mostrar os removidos
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $this->assertEquals(
            'SELECT `album`.* FROM `album` ORDER BY `id` ASC',
            $this->laminasDbAdapter->getSelect()->getSqlString($this->getAdapter()->getPlatform()),
            'showDeleted=false, useDeleted=false'
        );

        // Marca para usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true);
        $this->assertEquals(
            'SELECT `album`.* FROM `album` WHERE `album`.`deleted` = \'0\' ORDER BY `id` ASC',
            $this->laminasDbAdapter->getSelect()->getSqlString($this->getAdapter()->getPlatform()),
            'showDeleted=false, useDeleted=true'
        );

        // Marca para não usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(false);

        $this->assertEquals(
            'SELECT `album`.* FROM `album` WHERE `album`.`id` = \'1234\' ORDER BY `id` ASC',
            $this->laminasDbAdapter->getSelect(['id' => 1234])->getSqlString($this->getAdapter()->getPlatform())
        );
        $this->assertEquals(
            'SELECT `album`.* FROM `album` WHERE `album`.`texto` = \'textotextotexto\' ORDER BY `id` ASC',
            $this->laminasDbAdapter->getSelect(['texto' => 'textotextotexto'])->getSqlString(
                $this->getAdapter()->getPlatform()
            )
        );
    }

    /**
     * Tests Base->testGetSQlSelect()
     */
    public function testGetSQlSelectWithJoinLeft(): void
    {
        $select = $this->laminasDbAdapter->getTableSelect();
        $this->assertInstanceOf(Select::class, $select);
        $this->assertEquals($select->getSqlString(), $this->laminasDbAdapter->getTableSelect()->getSqlString());
        $this->assertEquals(
            'SELECT `album`.* FROM `album`',
            $select->getSqlString($this->getAdapter()->getPlatform())
        );

        $this->laminasDbAdapter->setUseJoin(true);
        $this->laminasDbAdapter->setTableJoin(
            [
                'test' => [
                    'table' => 'test_table',
                    'condition' => 'test_condition',
                    'columns' => ['test_column'],
                    'type' => Select::JOIN_LEFT
                ]
            ]
        );
        $select = $this->laminasDbAdapter->getTableSelect();
        $this->assertEquals(
            'SELECT `album`.*, `test_table`.`test_column` AS `test_column` FROM `album` LEFT JOIN `test_table` ON `test_condition`',
            $select->getSqlString($this->getAdapter()->getPlatform())
        );
    }

    /**
     * Tests Base->fetchAll()
     */
    public function testFetchAll(): void
    {
        $this->assertFalse($this->laminasDbAdapter->isUseHydrateResultSet());

        // O padrão é não usar o campo deleted
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $albuns = $this->laminasDbAdapter->fetchAll();
        $this->assertCount(4, $albuns, 'showDeleted=false, useDeleted=false');

        // Marca para mostrar os removidos e não usar o campo deleted
        $this->laminasDbAdapter->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->laminasDbAdapter->fetchAll(), 'showDeleted=true, useDeleted=false');

        // Marca pra não mostrar os removidos e usar o campo deleted
        $this->laminasDbAdapter->setShowDeleted(false)->setUseDeleted(true);
        $this->assertCount(3, $this->laminasDbAdapter->fetchAll(), 'showDeleted=false, useDeleted=true');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->laminasDbAdapter->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->laminasDbAdapter->fetchAll();
        $this->assertCount(4, $albuns, 'showDeleted=true, useDeleted=true');

        // Marca não mostrar os removidos
        $this->laminasDbAdapter->setUseDeleted(true)->setShowDeleted(false);

        $albuns = $this->defaultValues;
        unset($albuns[3]); // remove o deleted=1

        $fetchAll = $this->laminasDbAdapter->fetchAll();
        foreach ($fetchAll as $id => $row) {
            $fetchAll[$id] = $row->getArrayCopy();
        }
        $this->assertEquals($albuns, $fetchAll);

        // Marca mostrar os removidos
        $this->laminasDbAdapter->setShowDeleted(true);

        $fetchAll = $this->laminasDbAdapter->fetchAll();
        foreach ($fetchAll as $id => $row) {
            $fetchAll[$id] = $row->getArrayCopy();
        }
        $this->assertEquals($this->defaultValues, $fetchAll);
        $this->assertCount(4, $this->laminasDbAdapter->fetchAll());
        $this->laminasDbAdapter->setShowDeleted(false);
        $this->assertCount(3, $this->laminasDbAdapter->fetchAll());

        // Verifica o where
        $this->assertCount(2, $this->laminasDbAdapter->fetchAll(['artist' => $albuns[0]['artist']]));
        $this->assertNull($this->laminasDbAdapter->fetchAll(['artist' => $this->defaultValues[3]['artist']]));
    }

    /**
     * Tests Base->fetchAll()
     */
    public function testFetchAllHydrateResultSet(): void
    {
        $this->laminasDbAdapter->setUseHydrateResultSet(true);
        $this->assertTrue($this->laminasDbAdapter->isUseHydrateResultSet());

        // O padrão é não usar o campo deleted
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $albuns = $this->laminasDbAdapter->fetchAll();
        $this->assertCount(4, $albuns, 'showDeleted=false, useDeleted=false');

        // Marca para mostrar os removidos e não usar o campo deleted
        $this->laminasDbAdapter->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->laminasDbAdapter->fetchAll(), 'showDeleted=true, useDeleted=false');

        // Marca pra não mostrar os removidos e usar o campo deleted
        $this->laminasDbAdapter->setShowDeleted(false)->setUseDeleted(true);
        $this->assertCount(3, $this->laminasDbAdapter->fetchAll(), 'showDeleted=false, useDeleted=true');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->laminasDbAdapter->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->laminasDbAdapter->fetchAll();
        $this->assertCount(4, $albuns, 'showDeleted=true, useDeleted=true');

        // Marca não mostrar os removios
        $this->laminasDbAdapter->setUseDeleted(true)->setShowDeleted(false);

        $albuns = $this->defaultValues;
        unset($albuns[3]); // remove o deleted=1

        $fetchAll = $this->laminasDbAdapter->fetchAll();
        $this->assertInstanceOf(HydratingResultSet::class, $fetchAll);
        $fetchAll = $fetchAll->toArray();
        $this->assertEquals($albuns, $fetchAll);

        // Marca mostrar os removidos
        $this->laminasDbAdapter->setShowDeleted(true);

        $fetchAll = $this->laminasDbAdapter->fetchAll();
        $this->assertInstanceOf(HydratingResultSet::class, $fetchAll);
        $fetchAll = $fetchAll->toArray();

        $this->assertEquals($this->defaultValues, $fetchAll);
        $this->assertCount(4, $this->laminasDbAdapter->fetchAll());
        $this->laminasDbAdapter->setShowDeleted(false);
        $this->assertCount(3, $this->laminasDbAdapter->fetchAll());

        // Verifica o where
        $this->assertCount(2, $this->laminasDbAdapter->fetchAll(['artist' => $albuns[0]['artist']]));
        $this->assertNull($this->laminasDbAdapter->fetchAll(['artist' => $this->defaultValues[3]['artist']]));
    }

    /**
     * Tests Base->fetchRow()
     */
    public function testFetchRow(): void
    {
        $this->assertFalse($this->laminasDbAdapter->isUseHydrateResultSet());

        // Marca pra usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true);
        $this->laminasDbAdapter->setDefaultOrder(['id']);

        // Verifica os itens que existem
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 1]));
        $this->assertEquals($this->defaultValues[0], $this->laminasDbAdapter->fetchRow(['id' => 1])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 2]));
        $this->assertEquals($this->defaultValues[1], $this->laminasDbAdapter->fetchRow(['id' => 2])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 3]));
        $this->assertEquals($this->defaultValues[2], $this->laminasDbAdapter->fetchRow(['id' => 3])->getArrayCopy());

        // Verifica o item removido
        $this->laminasDbAdapter->setShowDeleted(true);
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 4]));
        $this->assertEquals($this->defaultValues[3], $this->laminasDbAdapter->fetchRow(['id' => 4])->getArrayCopy());
        $this->laminasDbAdapter->setShowDeleted(false);
    }

    /**
     * Tests Base->fetchRow()
     */
    public function testFetchRowHydrateResultaSet(): void
    {
        $this->laminasDbAdapter->setUseHydrateResultSet(true);
        $this->assertTrue($this->laminasDbAdapter->isUseHydrateResultSet());

        // Marca pra usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true);
        $this->laminasDbAdapter->setDefaultOrder(['id']);

        // Verifica os itens que existem
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 1]));
        $this->assertEquals($this->defaultValues[0], $this->laminasDbAdapter->fetchRow(['id' => 1])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 2]));
        $this->assertEquals($this->defaultValues[1], $this->laminasDbAdapter->fetchRow(['id' => 2])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 3]));
        $this->assertEquals($this->defaultValues[2], $this->laminasDbAdapter->fetchRow(['id' => 3])->getArrayCopy());

        // Verifica o item removido
        $this->laminasDbAdapter->setShowDeleted(true);
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 4]));
        $this->assertEquals($this->defaultValues[3], $this->laminasDbAdapter->fetchRow(['id' => 4])->getArrayCopy());
        $this->laminasDbAdapter->setShowDeleted(false);
    }

    /**
     * Tests Base->fetchRow()
     */
    public function testFetchRowWithIntegerKey(): void
    {
        $this->laminasDbAdapter->setTableKey(['id' => LaminasDbAdapter::KEY_INTEGER]);

        // Marca pra usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true);
        $this->laminasDbAdapter->setDefaultOrder(['id']);

        // Verifica os itens que existem
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 1]));
        $this->assertEquals($this->defaultValues[0], $this->laminasDbAdapter->fetchRow(['id' => 1])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 2]));
        $this->assertEquals($this->defaultValues[1], $this->laminasDbAdapter->fetchRow(['id' => 2])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 3]));
        $this->assertEquals($this->defaultValues[2], $this->laminasDbAdapter->fetchRow(['id' => 3])->getArrayCopy());

        // Verifica o item removido
        $this->laminasDbAdapter->setShowDeleted(true);
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 4]));
        $this->assertEquals($this->defaultValues[3], $this->laminasDbAdapter->fetchRow(['id' => 4])->getArrayCopy());
        $this->laminasDbAdapter->setShowDeleted(false);
    }

    /**
     * Tests Base->fetchRow()
     */
    public function testFetchRowWithStringKey(): void
    {
        $this->dropTables();
        $this->createTables(['album_string']);
        $defaultValues = [
            [
                'id' => 'A',
                'artist' => 'Rush',
                'title' => 'Rush',
                'deleted' => 0
            ],
            [
                'id' => 'B',
                'artist' => 'Rush',
                'title' => 'Moving Pictures',
                'deleted' => 0
            ],
            [
                'id' => 'C',
                'artist' => 'Dream Theater',
                'title' => 'Images And Words',
                'deleted' => 0
            ],
            [
                'id' => 'D',
                'artist' => 'Claudia Leitte',
                'title' => 'Exttravasa',
                'deleted' => 1
            ]
        ];
        foreach ($defaultValues as $row) {
            $this->getAdapter()->query(
                "INSERT into {$this->tableName}({$this->tableKeyName}, artist, title, deleted)
                                        VALUES (
                                        '{$row['id']}',
                                        '{$row['artist']}',
                                        '{$row['title']}',
                                        {$row['deleted']}
                                        );",
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        $this->laminasDbAdapter->setTableKey(['id' => LaminasDbAdapter::KEY_STRING]);

        // Marca pra usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true);

        $this->laminasDbAdapter->setDefaultOrder(['id']);

        // Verifica os itens que existem
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 'A']));
        $this->assertEquals($defaultValues[0], $this->laminasDbAdapter->fetchRow(['id' => 'A'])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 'B']));
        $this->assertEquals($defaultValues[1], $this->laminasDbAdapter->fetchRow(['id' => 'B'])->getArrayCopy());
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 'C']));
        $this->assertEquals($defaultValues[2], $this->laminasDbAdapter->fetchRow(['id' => 'C'])->getArrayCopy());

        // Verifica o item removido
        $this->laminasDbAdapter->setShowDeleted(true);
        $this->assertInstanceOf(ArrayObject::class, $this->laminasDbAdapter->fetchRow(['id' => 'D']));
        $this->assertEquals($defaultValues[3], $this->laminasDbAdapter->fetchRow(['id' => 'D'])->getArrayCopy());
        $this->laminasDbAdapter->setShowDeleted(false);
    }

    /**
     * Tests Base->fetchRow()
     */
    public function testFetchRowWithMultipleKey(): void
    {
        $this->dropTables();
        $this->createTables(['album_array']);
        $defaultValues = [
            [
                'id_int' => 1,
                'id_char' => 'A',
                'artist' => 'Rush',
                'title' => 'Rush',
                'deleted' => 0
            ],
            [
                'id_int' => 2,
                'id_char' => 'B',
                'artist' => 'Rush',
                'title' => 'Moving Pictures',
                'deleted' => 0
            ],
            [
                'id_int' => 3,
                'id_char' => 'C',
                'artist' => 'Dream Theater',
                'title' => 'Images And Words',
                'deleted' => 0
            ],
            [
                'id_int' => 4,
                'id_char' => 'D',
                'artist' => 'Claudia Leitte',
                'title' => 'Exttravasa',
                'deleted' => 1
            ]
        ];
        foreach ($defaultValues as $row) {
            $this->getAdapter()->query(
                "INSERT into album (id_int, id_char, artist, title, deleted)
                                        VALUES (
                                        '{$row['id_int']}',
                                        '{$row['id_char']}',
                                        '{$row['artist']}',
                                        '{$row['title']}',
                                        {$row['deleted']}
                                        );",
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        $this->laminasDbAdapter->setTableKey(['id' => LaminasDbAdapter::KEY_STRING]);

        // Marca pra usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true);

        $this->laminasDbAdapter->setDefaultOrder(['id_int', 'id_char']);

        // Verifica os itens que existem
        $this->assertInstanceOf(
            ArrayObject::class,
            $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1])
        );
        $this->assertEquals(
            $defaultValues[0],
            $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1])->getArrayCopy()
        );
        $this->assertInstanceOf(
            ArrayObject::class,
            $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2])
        );
        $this->assertEquals(
            $defaultValues[1],
            $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2])->getArrayCopy()
        );
        $this->assertInstanceOf(
            ArrayObject::class,
            $this->laminasDbAdapter->fetchRow(['id_char' => 'C', 'id_int' => 3])
        );
        $this->assertEquals(
            $defaultValues[2],
            $this->laminasDbAdapter->fetchRow(['id_char' => 'C', 'id_int' => 3])->getArrayCopy()
        );

        $this->assertNull($this->laminasDbAdapter->fetchRow(['id_char' => 'C', 'id_int' => 2]));

        // Verifica o item removido
        $this->laminasDbAdapter->setShowDeleted(true);
        $this->assertInstanceOf(
            ArrayObject::class,
            $this->laminasDbAdapter->fetchRow(['id_char' => 'D', 'id_int' => 4])
        );
        $this->assertEquals(
            $defaultValues[3],
            $this->laminasDbAdapter->fetchRow(['id_char' => 'D', 'id_int' => 4])->getArrayCopy()
        );
        $this->laminasDbAdapter->setShowDeleted(false);
    }

    /**
     * Tests Db->insert()
     */
    public function testInsert(): void
    {
        $this->dropTables();
        $this->createTables(['album']);
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $this->assertNull($this->laminasDbAdapter->fetchAll(), 'Verifica se há algum registro pregravado');

        $this->assertFalse($this->laminasDbAdapter->insert([]), 'Verifica inclusão inválida 1');

        $row = [
            'artist' => 'Rush',
            'title' => 'Rush',
            'deleted' => '0'
        ];

        $id = $this->laminasDbAdapter->insert($row);
        $this->assertEquals(1, $id, 'Verifica a chave criada=1');

        $this->assertNotNull($this->laminasDbAdapter->fetchAll(), 'Verifica o fetchAll não vazio');
        $this->assertEquals($row, $this->laminasDbAdapter->getLastInsertSet(), 'Verifica o set do ultimo insert');
        $this->assertCount(1, $this->laminasDbAdapter->fetchAll(), 'Verifica se apenas um registro foi adicionado');

        $row = array_merge(['id' => $id], $row);

        $this->assertEquals(
            [new ArrayObject($row)],
            $this->laminasDbAdapter->fetchAll(),
            'Verifica se o registro adicionado corresponde ao original pelo fetchAll()'
        );
        $this->assertEquals(
            new ArrayObject($row),
            $this->laminasDbAdapter->fetchRow(['id' => 1]),
            'Verifica se o registro adicionado corresponde ao original pelo fetchRow()'
        );

        $row = [
            'id' => 2,
            'artist' => 'Rush',
            'title' => 'Test For Echos',
            'deleted' => '0'
        ];

        $id = $this->laminasDbAdapter->insert($row);
        $this->assertEquals(2, $id, 'Verifica a chave criada=2');

        $this->assertCount(2, $this->laminasDbAdapter->fetchAll(), 'Verifica que há DOIS registro');
        $this->assertEquals(
            new ArrayObject($row),
            $this->laminasDbAdapter->fetchRow(['id' => 2]),
            'Verifica se o SEGUNDO registro adicionado corresponde ao original pelo fetchRow()'
        );
        $this->assertEquals($row, $this->laminasDbAdapter->getLastInsertSet());

        $row = [
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => '0'
        ];
        $id = $this->laminasDbAdapter->insert($row);
        $this->assertEquals(3, $id);
        $this->assertEquals(
            $row,
            $this->laminasDbAdapter->getLastInsertSet(),
            'Verifica se o TERCEIRO registro adicionado corresponde ao original pelo getLastInsertSet()'
        );

        $row = array_merge(['id' => $id], $row);

        $this->assertCount(3, $this->laminasDbAdapter->fetchAll());
        $this->assertEquals(
            new ArrayObject($row),
            $this->laminasDbAdapter->fetchRow(['id' => 3]),
            'Verifica se o TERCEIRO registro adicionado corresponde ao original pelo fetchRow()'
        );

        $id = $this->laminasDbAdapter->insert(['title' => new Expression('now()')]);
        $this->assertEquals(4, $id);
    }

    /**
     * Tests Db->update()
     */
    public function testUpdate(): void
    {
        $this->dropTables();
        $this->createTables(['album']);
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $this->assertEmpty($this->laminasDbAdapter->fetchAll(), 'tabela não está vazia');

        $row1 = [
            'id' => 1,
            'artist' => 'Não me altere',
            'title' => 'Presto',
            'deleted' => 0
        ];

        $row2 = [
            'id' => 2,
            'artist' => 'Rush',
            'title' => 'Rush',
            'deleted' => 0
        ];

        $this->laminasDbAdapter->insert($row1);
        $this->laminasDbAdapter->insert($row2);

        $this->assertNotNull($this->laminasDbAdapter->fetchAll());
        $this->assertCount(2, $this->laminasDbAdapter->fetchAll());
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 existe');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 existe');

        $rowUpdate = [
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
        ];

        $this->laminasDbAdapter->update($rowUpdate, 2);
        $rowUpdate['id'] = '2';
        $rowUpdate['deleted'] = '0';

        $this->assertNotNull($this->laminasDbAdapter->fetchAll());
        $this->assertCount(2, $this->laminasDbAdapter->fetchAll());
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($rowUpdate, $row->getArrayCopy(), 'Alterou o 2?');

        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'Alterou o 1?');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertNotEquals($row2, $row->getArrayCopy(), 'O 2 não é mais o mesmo?');

        $row = $row->getArrayCopy();
        unset($row['id'], $row['deleted']);
        $this->assertEquals(
            $row,
            $this->laminasDbAdapter->getLastUpdateSetAfter(),
            'Os dados diferentes foram os alterados?'
        );
        $this->assertEquals(
            ['title' => [$row2['title'], $row['title']]],
            $this->laminasDbAdapter->getLastUpdateDiff(),
            'As alterações foram detectadas corretamente?'
        );

        $this->assertEquals(0, $this->laminasDbAdapter->update([], 2));
        $this->assertEquals(0, $this->laminasDbAdapter->update(null, 2));
    }

    /**
     * Tests TableAdapter->delete()
     */
    public function testDelete(): void
    {
        // Apaga as tabelas
        $this->dropTables();
        $this->createTables(['album']);
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $this->assertEmpty($this->laminasDbAdapter->fetchAll(), 'tabela não está vazia');

        $row1 = [
            'id' => 1,
            'artist' => 'Rush',
            'title' => 'Presto',
            'deleted' => 0
        ];
        $row2 = [
            'id' => 2,
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => 0
        ];

        $this->laminasDbAdapter->insert($row1);
        $this->laminasDbAdapter->insert($row2);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 existe');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 existe');

        // Marca para usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true)->setShowDeleted(true);

        // Remove o registro
        $this->laminasDbAdapter->delete(1);
        $row1['deleted'] = 1;

        // Verifica se foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertEquals(1, $row['deleted'], 'row1 marcado como deleted');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe');

        // Marca para mostrar os removidos
        $this->laminasDbAdapter->setShowDeleted(true);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 ainda existe v1');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v1');

        // Marca para remover o registro da tabela
        $this->laminasDbAdapter->setUseDeleted(false);

        // Remove o registro qwue não existe
        $this->laminasDbAdapter->delete(3);

        // Verifica se ele foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 ainda existe v2');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v2');

        // Remove o registro
        $this->laminasDbAdapter->delete(1);

        // Verifica se ele foi removido
        $this->assertNull($this->laminasDbAdapter->fetchRow(['id' => 1]), 'row1 não existe v3');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v3');
    }

    /**
     * Tests TableAdapter->delete()
     */
    public function testDeleteIntegerKey(): void
    {
        $this->dropTables();
        $this->createTables(['album']);
        $this->laminasDbAdapter->setDefaultOrder(['id']);
        $this->assertEmpty($this->laminasDbAdapter->fetchAll(), 'tabela não está vazia');

        $this->laminasDbAdapter->setTableKey(['id' => LaminasDbAdapter::KEY_INTEGER]);

        // Abaixo é igual ao testDelete
        $row1 = [
            'id' => 1,
            'artist' => 'Rush',
            'title' => 'Presto',
            'deleted' => 0
        ];
        $row2 = [
            'id' => 2,
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => 0
        ];

        $this->laminasDbAdapter->insert($row1);
        $this->laminasDbAdapter->insert($row2);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 existe');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 existe');

        // Marca para usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true)->setShowDeleted(true);

        // Remove o registro
        $this->laminasDbAdapter->delete(1);
        $row1['deleted'] = 1;

        // Verifica se foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertEquals(1, $row['deleted'], 'row1 marcado como deleted');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe');

        // Marca para mostrar os removidos
        $this->laminasDbAdapter->setShowDeleted(true);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 ainda existe v1');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v1');

        // Marca para remover o registro da tabela
        $this->laminasDbAdapter->setUseDeleted(false);

        // Remove o registro qwue não existe
        $this->laminasDbAdapter->delete(3);

        // Verifica se ele foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 ainda existe v2');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v2');

        // Remove o registro
        $this->laminasDbAdapter->delete(1);

        // Verifica se ele foi removido
        $this->assertNull($this->laminasDbAdapter->fetchRow(['id' => 1]), 'row1 não existe v3');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v3');
    }

    /**
     * Tests TableAdapter->delete()
     */
    public function testDeleteStringKey(): void
    {
        // Cria a tabela com chave string
        $this->laminasDbAdapter->setTableKey(['id' => LaminasDbAdapter::KEY_STRING]);
        $this->dropTables();
        $this->createTables(['album_string']);
        $this->laminasDbAdapter->setDefaultOrder(['id']);

        // Abaixo é igual ao testDelete trocando 1, 2 por A, B
        $row1 = [
            'id' => 'A',
            'artist' => 'Rush',
            'title' => 'Presto',
            'deleted' => 0
        ];
        $row2 = [
            'id' => 'B',
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => 0
        ];

        $this->laminasDbAdapter->insert($row1);
        $this->laminasDbAdapter->insert($row2);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'A']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 existe');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'B']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 existe');

        // Marca para usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true)->setShowDeleted(true);

        // Remove o registro
        $this->laminasDbAdapter->delete('A');
        $row1['deleted'] = 1;

        // Verifica se foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'A']);
        $this->assertEquals(1, $row['deleted'], 'row1 marcado como deleted');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'B']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe');

        // Marca para mostrar os removidos
        $this->laminasDbAdapter->setShowDeleted(true);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'A']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 ainda existe v1');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'B']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v1');

        // Marca para remover o registro da tabela
        $this->laminasDbAdapter->setUseDeleted(false);

        // Remove o registro qwue não existe
        $this->laminasDbAdapter->delete('C');

        // Verifica se ele foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'A']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 ainda existe v2');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'B']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v2');

        // Remove o registro
        $this->laminasDbAdapter->delete('A');

        // Verifica se ele foi removido
        $this->assertNull($this->laminasDbAdapter->fetchRow(['id' => 'A']), 'row1 não existe v3');
        $row = $this->laminasDbAdapter->fetchRow(['id' => 'B']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v3');
    }

    /**
     * Acesso de chave multiplica com acesso simples
     */
    public function testDeleteInvalidArrayKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->laminasDbAdapter->setTableKey(
            [LaminasDbAdapter::KEY_INTEGER => 'id_int', LaminasDbAdapter::KEY_STRING => 'id_char']
        );
        $this->laminasDbAdapter->delete('A');
    }

    public function testDeleteInvalidArrayMultiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->laminasDbAdapter->setTableKey(
            [
                'id_int' => LaminasDbAdapter::KEY_INTEGER,
                'id_char2' => LaminasDbAdapter::KEY_STRING
            ]
        );
        $this->laminasDbAdapter->delete('A');
    }

    public function testDeleteInvalidArraySingleKey(): void
    {
        $this->expectException(LogicException::class);
        $this->laminasDbAdapter->setTableKey(
            [
                'id_int' => LaminasDbAdapter::KEY_INTEGER,
                'id_char2' => LaminasDbAdapter::KEY_STRING
            ]
        );
        $this->laminasDbAdapter->delete(['id_int' => 'A']);
    }

    public function testDeleteArrayKey(): void
    {
        // Cria a tabela com chave string
        $this->laminasDbAdapter->setTableKey(
            ['id_int' => LaminasDbAdapter::KEY_INTEGER, 'id_char' => LaminasDbAdapter::KEY_STRING]
        );
        $this->dropTables();
        $this->createTables(['album_array']);
        $this->laminasDbAdapter->setUseAllKeys(false);
        $this->laminasDbAdapter->setDefaultOrder(['id_int', 'id_char']);

        // Abaixo é igual ao testDelete trocando 1, 2 por A, B
        $row1 = [
            'id_int' => 1,
            'id_char' => 'A',
            'artist' => 'Rush',
            'title' => 'Presto',
            'deleted' => 0
        ];
        $row2 = [
            'id_int' => 2,
            'id_char' => 'B',
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => 0
        ];

        $this->laminasDbAdapter->insert($row1);
        $this->laminasDbAdapter->insert($row2);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 existe');
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 existe');

        // Marca para usar o campo deleted
        $this->laminasDbAdapter->setUseDeleted(true)->setShowDeleted(true);

        // Remove o registro
        $this->laminasDbAdapter->delete(['id_char' => 'A']);
        $row1['deleted'] = 1;

        // Verifica se foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals(1, $row['deleted'], 'row1 marcado como deleted');

        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 ainda existe v1');

        // Marca para mostrar os removidos
        $this->laminasDbAdapter->setShowDeleted(true);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1]);
        $this->assertInstanceOf(ArrayObject::class, $row, 'row1 ainda existe v1');
        $this->assertEquals($row1, $row->getArrayCopy());
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2]);
        $this->assertInstanceOf(ArrayObject::class, $row, 'row2 ainda existe v1');
        $this->assertEquals($row2, $row->getArrayCopy());

        // Marca para remover o registro da tabela
        $this->laminasDbAdapter->setUseDeleted(false);

        // Remove o registro que não existe
        $this->laminasDbAdapter->delete(['id_char' => 'C']);

        // Verifica se ele foi removido
        $this->assertNotEmpty(
            $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1]),
            'row1 ainda existe v3'
        );
        $this->assertNotEmpty(
            $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2]),
            'row2 ainda existe v3'
        );

        // Remove o registro
        $this->laminasDbAdapter->delete(['id_char' => 'A']);

        // Verifica se ele foi removido
        $this->assertNull($this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1]), 'row1 não existe v4');
        $this->assertNotEmpty(
            $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2]),
            'row2 ainda existe v4'
        );
    }

    /**
     * Tests TableAdapter->update()
     */
    public function testUpdateArrayKey(): void
    {
        // Cria a tabela com chave string
        $this->laminasDbAdapter->setTableKey(
            [
                'id_int' => LaminasDbAdapter::KEY_INTEGER,
                'artist' => LaminasDbAdapter::KEY_STRING,
                'id_char' => LaminasDbAdapter::KEY_STRING
            ]
        );
        $this->dropTables();
        $this->createTables(['album_array_two']);
        $this->laminasDbAdapter->setUseAllKeys(true);
        $this->laminasDbAdapter->setDefaultOrder(['id_int', 'id_char', 'artist']);

        // Abaixo é igual ao testDelete trocando 1, 2 por A, B
        $row1 = [
            'id_int' => 1,
            'id_char' => 'A',
            'artist' => 'Rush',
            'title' => 'Presto',
            'deleted' => 0
        ];
        $row2 = [
            'id_int' => 2,
            'id_char' => 'B',
            'artist' => 'Rush',
            'title' => 'Moving Pictures',
            'deleted' => 0
        ];

        $this->laminasDbAdapter->insert($row1);
        $this->laminasDbAdapter->insert($row2);

        // Verifica se o registro existe
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1, 'artist' => 'Rush']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row1, $row->getArrayCopy(), 'row1 existe');
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'B', 'id_int' => 2, 'artist' => 'Rush']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals($row2, $row->getArrayCopy(), 'row2 existe');


        // atualizar o registro
        $this->laminasDbAdapter->update(
            ['title' => 'New title'],
            ['id_char' => 'A', 'id_int' => 1, 'artist' => 'Rush']
        );

        // Verifica se foi removido
        $row = $this->laminasDbAdapter->fetchRow(['id_char' => 'A', 'id_int' => 1, 'artist' => 'Rush']);
        $this->assertInstanceOf(ArrayObject::class, $row);
        $this->assertEquals('New title', $row['title'], 'row1 atualizado ');
    }
}
