<?php

namespace RealejoTest\Sdk\Db;

use DateTime;
use Laminas\Db\Adapter\Adapter;
use Laminas\Dom\Document\Query as DomQuery;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Realejo\Sdk\Cache\CacheService;
use Realejo\Sdk\Db\LaminasDbAdapter;
use Realejo\Sdk\Db\Paginator;
use RealejoTest\Sdk\Test\DataTrait;
use RealejoTest\Sdk\Test\DbTrait;

class RepositoryTest extends TestCase
{
    use DataTrait;
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
     * @var RepositoryConcrete
     */
    private $repository;

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

    public function insertDefaultRows(): self
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
        return $this;
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

        $this->repository = new RepositoryConcrete();
        $this->repository->getDbAdapter()->setAdapter($this->getAdapter());

        $cacheService = new CacheService();
        $cacheService->setCacheDir($this->getDataDir() . '/cache');
        $this->repository->setCache($cacheService->getFrontend());

        // Remove as pastas criadas
        $this->clearDataDir();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->dropTables();

        unset($this->repository);

        $this->clearDataDir();
    }

    public function testFindAll(): void
    {
        // O padrão é não usar o campo deleted
        $this->repository->getDbAdapter()->setDefaultOrder(['id']);
        $albuns = $this->repository->findAll();
        $this->assertCount(4, $albuns, 'showDeleted=false, useDeleted=false');

        // Marca para mostrar os removidos e não usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->repository->findAll(), 'showDeleted=true, useDeleted=false');

        // Marca pra não mostrar os removidos e usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(false)->setUseDeleted(true);
        $this->assertCount(3, $this->repository->findAll(), 'showDeleted=false, useDeleted=true');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->repository->findAll();
        $this->assertCount(4, $albuns, 'showDeleted=true, useDeleted=true');

        // Marca não mostrar os removios
        $this->repository->getDbAdapter()->setUseDeleted(true)->setShowDeleted(false);

        $albuns = $this->defaultValues;
        unset($albuns[3]); // remove o deleted=1

        $findAll = $this->repository->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $this->assertEquals($albuns, $findAll);

        // Marca mostrar os removidos
        $this->repository->getDbAdapter()->setShowDeleted(true);

        $findAll = $this->repository->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $this->assertEquals($this->defaultValues, $findAll);
        $this->assertCount(4, $this->repository->findAll());
        $this->repository->getDbAdapter()->setShowDeleted(false);
        $this->assertCount(3, $this->repository->findAll());

        // Verifica o where
        $this->assertCount(2, $this->repository->findAll(['artist' => $albuns[0]['artist']]));
        $this->assertNull($this->repository->findAll(['artist' => $this->defaultValues[3]['artist']]));

        // Verifica o paginator com o padrão
        $paginator = $this->repository->findPaginated();

        $temp = [];
        foreach ($paginator->getIterator() as $p) {
            $temp[] = $p->getArrayCopy();
        }

        $findAll = $this->repository->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $paginator = json_encode($temp);
        $this->assertNotEquals(json_encode($this->defaultValues), $paginator);
        $this->assertEquals(json_encode($findAll), $paginator, 'retorno do paginator é igual');

        // Verifica o paginator alterando o paginator
        $this->repository->getPaginatorOptions()
            ->setPageRange(2)
            ->setCurrentPageNumber(1)
            ->setItemCountPerPage(2);
        $paginator = $this->repository->findPaginated();

        $temp = [];
        foreach ($paginator->getCurrentItems() as $p) {
            $temp[] = $p->getArrayCopy();
        }
        $paginator = json_encode($temp);

        $this->assertNotEquals(json_encode($this->defaultValues), $paginator);
        $fetchAll = $this->repository->findPaginated(null, null, 2);
        $temp = [];
        foreach ($fetchAll as $p) {
            $temp[] = $p->toArray();
        }
        $fetchAll = $temp;
        $this->assertEquals(json_encode($fetchAll), $paginator);

        // Apaga qualquer cache
        $this->assertTrue($this->repository->getCache()->flush(), 'apaga o cache');

        // Define exibir os deletados
        $this->repository->getDbAdapter()->setShowDeleted(true);

        // Liga o cache
        $this->repository->setUseCache(true);
        $findAll = $this->repository->findAll();
        $temp = [];
        foreach ($findAll as $p) {
            $temp[] = $p->toArray();
        }
        $findAll = $temp;
        $this->assertEquals($this->defaultValues, $findAll, 'fetchAll está igual ao defaultValues');
        $this->assertCount(4, $findAll, 'Deve conter 4 registros');

        // Grava um registro "sem o cache saber"
        $this->repository->getDbAdapter()->getTableGateway()->insert(
            [
                'id' => 10,
                'artist' => 'nao existo por enquanto',
                'title' => 'bla bla',
                'deleted' => 0
            ]
        );

        $this->assertCount(
            4,
            $this->repository->findAll(),
            'Deve conter 4 registros depois do insert "sem o cache saber"'
        );
        $this->assertTrue($this->repository->getCache()->flush(), 'limpa o cache');
        $this->assertCount(5, $this->repository->findAll(), 'Deve conter 5 registros');

        // Define não exibir os deletados
        $this->repository->getDbAdapter()->setShowDeleted(false);
        $this->assertCount(4, $this->repository->findAll(), 'Deve conter 4 registros showDeleted=false');

        // Apaga um registro "sem o cache saber"
        $this->repository->getDbAdapter()->getTableGateway()->delete("id=10");
        $this->repository->getDbAdapter()->setShowDeleted(true);
        $this->assertCount(5, $this->repository->findAll(), 'Deve conter 5 registros');
        $this->assertTrue($this->repository->getCache()->flush(), 'apaga o cache');
        $this->assertCount(4, $this->repository->findAll(), 'Deve conter 4 registros 4');
    }

    public function testFindOne(): void
    {
        // Marca pra usar o campo deleted
        $this->repository->getDbAdapter()->setUseDeleted(true);

        $this->repository->getDbAdapter()->setDefaultOrder('id');

        // Verifica os itens que existem
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $this->repository->findOne(1));
        $this->assertEquals($this->defaultValues[0], $this->repository->findOne(1)->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $this->repository->findOne(2));
        $this->assertEquals($this->defaultValues[1], $this->repository->findOne(2)->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $this->repository->findOne(3));
        $this->assertEquals($this->defaultValues[2], $this->repository->findOne(3)->toArray());
        $this->assertEmpty($this->repository->findOne(4));

        // Verifica o item removido
        $this->repository->getDbAdapter()->setShowDeleted(true);
        $findOne = $this->repository->findOne(4);
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $findOne);
        $this->assertEquals($this->defaultValues[3], $findOne->toArray());
        $this->repository->getDbAdapter()->setShowDeleted(false);
    }

    public function testFindAssoc(): void
    {
        $this->repository->getDbAdapter()->setDefaultOrder(['id']);

        // O padrão é não usar o campo deleted
        $albuns = $this->repository->findAssoc();
        $this->assertCount(4, $albuns, 'showDeleted=false, useDeleted=false');
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[1]);
        $this->assertEquals($this->defaultValues[0], $albuns[1]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[2]);
        $this->assertEquals($this->defaultValues[1], $albuns[2]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[3]);
        $this->assertEquals($this->defaultValues[2], $albuns[3]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[4]);
        $this->assertEquals($this->defaultValues[3], $albuns[4]->toArray());

        // Marca para mostrar os removidos e não usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->repository->findAssoc(), 'showDeleted=true, useDeleted=false');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->repository->findAssoc();
        $this->assertCount(4, $albuns, 'showDeleted=true, useDeleted=true');
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[1]);
        $this->assertEquals($this->defaultValues[0], $albuns[1]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[2]);
        $this->assertEquals($this->defaultValues[1], $albuns[2]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[3]);
        $this->assertEquals($this->defaultValues[2], $albuns[3]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[4]);
        $this->assertEquals($this->defaultValues[3], $albuns[4]->toArray());
    }

    public function testFindAssocWithMultipleKeys(): void
    {
        $this->repository->getDbAdapter()->setTableKey([$this->tableKeyName, 'naoexisto']);

        $this->repository->getDbAdapter()->setDefaultOrder(['id']);

        // O padrão é não usar o campo deleted
        $albuns = $this->repository->findAssoc();
        $this->assertCount(4, $albuns, 'showDeleted=false, useDeleted=false');
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[1]);
        $this->assertEquals($this->defaultValues[0], $albuns[1]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[2]);
        $this->assertEquals($this->defaultValues[1], $albuns[2]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[3]);
        $this->assertEquals($this->defaultValues[2], $albuns[3]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[4]);
        $this->assertEquals($this->defaultValues[3], $albuns[4]->toArray());

        // Marca para mostrar os removidos e não usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->repository->findAssoc(), 'showDeleted=true, useDeleted=false');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->repository->getDbAdapter()->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->repository->findAssoc();
        $this->assertCount(4, $albuns, 'showDeleted=true, useDeleted=true');
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[1]);
        $this->assertEquals($this->defaultValues[0], $albuns[1]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[2]);
        $this->assertEquals($this->defaultValues[1], $albuns[2]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[3]);
        $this->assertEquals($this->defaultValues[2], $albuns[3]->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $albuns[4]);
        $this->assertEquals($this->defaultValues[3], $albuns[4]->toArray());
    }

    public function testServiceLocator(): void
    {
        $fakeServiceLocator = new FakeServiceLocator();
        $service = new RepositoryConcrete();
        $service->setServiceLocator($fakeServiceLocator);
        $this->assertInstanceOf(FakeServiceLocator::class, $service->getServiceLocator());
        $this->assertInstanceOf(ContainerInterface::class, $service->getServiceLocator());

        $cacheService = new CacheService();
        $cacheService->setCacheDir($this->getDataDir() . '/cache');
        $service->setCache($cacheService->getFrontend());

        $dbAdapter = $service->getDbAdapter();
        $this->assertInstanceOf(LaminasDbAdapter::class, $dbAdapter);
        $this->assertInstanceOf(FakeServiceLocator::class, $dbAdapter->getServiceLocator());
        $this->assertInstanceOf(ContainerInterface::class, $dbAdapter->getServiceLocator());

        $this->assertNull($service->getFromServiceLocator('\DateTime'));

        $realServiceLocator = new ServiceManager();
        $service->setServiceLocator($realServiceLocator);
        $this->assertInstanceOf(DateTime::class, $service->getFromServiceLocator('\DateTime'));

        $fakeObject = (object)['id' => 1];
        $service->getServiceLocator()->setService('fake', $fakeObject);
        $this->assertTrue($service->getServiceLocator()->has('fake'));
        $this->assertEquals($fakeObject, $service->getServiceLocator()->get('fake'));
    }

    public function testFindPaginated(): void
    {
        $this->repository->getDbAdapter()->setDefaultOrder(['id']);
        $albuns = $this->repository->findPaginated();
        $this->assertInstanceOf(Paginator::class, $albuns);
        $this->assertCount(4, $albuns->getCurrentItems());

        $this->assertFalse($this->repository->getUseCache());

        // Liga o cache
        $this->repository->setUseCache(true);
        $this->assertTrue($this->repository->getUseCache());

        // Verifica o paginator com o padrão
        $paginator = $this->repository->findPaginated();

        // verifica se vai utilizar o mesmo cache id quando realizar a mesma consulta, pois estava criando novo id e nunca
        // utilizando o cache no paginator
        $oldId = $this->repository->getCache()->getIterator()->key();
        $fetchAll = $this->repository->setUseCache(true)->findPaginated();
        $this->assertEquals($oldId, $this->repository->getCache()->getIterator()->key());
        // Apaga qualquer cache
        $this->assertTrue($this->repository->getCache()->flush(), 'apaga o cache');

        $temp = [];
        foreach ($paginator->getIterator() as $p) {
            $temp[] = $p->getArrayCopy();
        }

        $findAll = $this->repository->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $paginator = json_encode($temp);
        $this->assertEquals(json_encode($findAll), $paginator, 'retorno do paginator é igual');

        // Verifica o paginator alterando o paginator
        $this->repository->getPaginatorOptions()
            ->setPageRange(2)
            ->setCurrentPageNumber(1)
            ->setItemCountPerPage(2);
        $paginator = $this->repository->findPaginated();

        $temp = [];
        foreach ($paginator->getCurrentItems() as $p) {
            $temp[] = $p->getArrayCopy();
        }
        $paginator = json_encode($temp);

        $this->assertNotEquals(json_encode($this->defaultValues), $paginator);
        $fetchAll = $this->repository->findPaginated(null, null, 2);
        $temp = [];
        foreach ($fetchAll as $p) {
            $temp[] = $p->toArray();
        }
        $fetchAll = $temp;
        $this->assertEquals(json_encode($fetchAll), $paginator);

        // verifica se vai utilizar o mesmo cache id quando realizar a mesma consulta, pois estava criando nova e nunca
        // utilizando o cache no paginator
        $oldId = $this->repository->getCache()->getIterator()->key();
        $fetchAll = $this->repository->setUseCache(true)->findPaginated(null, null, 2);
        $this->assertEquals($oldId, $this->repository->getCache()->getIterator()->key());
    }
}
