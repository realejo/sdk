<?php

namespace RealejoTest\Service;

use Psr\Container\ContainerInterface;
use Realejo\Cache\CacheService;
use Realejo\Service\MapperAbstract;
use Realejo\Service\Metadata\MetadataService;
use RealejoTest\BaseTestCase;
use Laminas\Db\Adapter\Adapter;
use Laminas\Dom\Query as DomQuery;
use Laminas\ServiceManager\ServiceManager;

class ServiceTest extends BaseTestCase
{
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
     * @var ServiceConcrete
     */
    private $service;

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

    /**
     * @return self
     */
    public function insertDefaultRows()
    {
        foreach ($this->defaultValues as $row) {
            $this->getAdapter()->query("INSERT into {$this->tableName}({$this->tableKeyName}, artist, title, deleted)
                                        VALUES (
                                            {$row[$this->tableKeyName]},
                                            '{$row['artist']}',
                                            '{$row['title']}',
                                            {$row['deleted']}
                                        );", Adapter::QUERY_MODE_EXECUTE);
        }
        return $this;
    }

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->dropTables()->createTables()->insertDefaultRows();

        $this->service = new ServiceConcrete();

        $cacheService = new CacheService();
        $cacheService->setCacheDir($this->getDataDir() . '/cache');
        $this->service->setCache($cacheService->getFrontend());

        // Remove as pastas criadas
        $this->clearApplicationData();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        parent::tearDown();

        $this->dropTables();

        unset($this->service);

        $this->clearApplicationData();
    }

    /**
     * Tests Base->fetchAll()
     */
    public function testFindAll()
    {
        // O padrão é não usar o campo deleted
        $this->service->getMapper()->setOrder('id');
        $albuns = $this->service->findAll();
        $this->assertCount(4, $albuns, 'showDeleted=false, useDeleted=false');

        // Marca para mostrar os removidos e não usar o campo deleted
        $this->service->getMapper()->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->service->findAll(), 'showDeleted=true, useDeleted=false');

        // Marca pra não mostrar os removidos e usar o campo deleted
        $this->service->getMapper()->setShowDeleted(false)->setUseDeleted(true);
        $this->assertCount(3, $this->service->findAll(), 'showDeleted=false, useDeleted=true');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->service->getMapper()->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->service->findAll();
        $this->assertCount(4, $albuns, 'showDeleted=true, useDeleted=true');

        // Marca não mostrar os removios
        $this->service->getMapper()->setUseDeleted(true)->setShowDeleted(false);

        $albuns = $this->defaultValues;
        unset($albuns[3]); // remove o deleted=1

        $findAll = $this->service->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $this->assertEquals($albuns, $findAll);

        // Marca mostrar os removidos
        $this->service->getMapper()->setShowDeleted(true);

        $findAll = $this->service->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $this->assertEquals($this->defaultValues, $findAll);
        $this->assertCount(4, $this->service->findAll());
        $this->service->getMapper()->setShowDeleted(false);
        $this->assertCount(3, $this->service->findAll());

        // Verifica o where
        $this->assertCount(2, $this->service->findAll(['artist' => $albuns[0]['artist']]));
        $this->assertNull($this->service->findAll(['artist' => $this->defaultValues[3]['artist']]));

        // Verifica o paginator com o padrão
        $paginator = $this->service->findPaginated();

        $temp = [];
        foreach ($paginator->getIterator() as $p) {
            $temp[] = $p->getArrayCopy();
        }

        $findAll = $this->service->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $paginator = json_encode($temp);
        $this->assertNotEquals(json_encode($this->defaultValues), $paginator);
        $this->assertEquals(json_encode($findAll), $paginator, 'retorno do paginator é igual');

        // Verifica o paginator alterando o paginator
        $this->service->getPaginatorOptions()
            ->setPageRange(2)
            ->setCurrentPageNumber(1)
            ->setItemCountPerPage(2);
        $paginator = $this->service->findPaginated();

        $temp = [];
        foreach ($paginator->getCurrentItems() as $p) {
            $temp[] = $p->getArrayCopy();
        }
        $paginator = json_encode($temp);

        $this->assertNotEquals(json_encode($this->defaultValues), $paginator);
        $fetchAll = $this->service->findPaginated(null, null, 2);
        $temp = [];
        foreach ($fetchAll as $p) {
            $temp[] = $p->toArray();
        }
        $fetchAll = $temp;
        $this->assertEquals(json_encode($fetchAll), $paginator);

        // Apaga qualquer cache
        $this->assertTrue($this->service->getCache()->flush(), 'apaga o cache');

        // Define exibir os deletados
        $this->service->getMapper()->setShowDeleted(true);

        // Liga o cache
        $this->service->setUseCache(true);
        $findAll = $this->service->findAll();
        $temp = [];
        foreach ($findAll as $p) {
            $temp[] = $p->toArray();
        }
        $findAll = $temp;
        $this->assertEquals($this->defaultValues, $findAll, 'fetchAll está igual ao defaultValues');
        $this->assertCount(4, $findAll, 'Deve conter 4 registros');

        // Grava um registro "sem o cache saber"
        $this->service->getMapper()->getTableGateway()->insert([
            'id' => 10,
            'artist' => 'nao existo por enquanto',
            'title' => 'bla bla',
            'deleted' => 0
        ]);

        $this->assertCount(4, $this->service->findAll(),
            'Deve conter 4 registros depois do insert "sem o cache saber"');
        $this->assertTrue($this->service->getCache()->flush(), 'limpa o cache');
        $this->assertCount(5, $this->service->findAll(), 'Deve conter 5 registros');

        // Define não exibir os deletados
        $this->service->getMapper()->setShowDeleted(false);
        $this->assertCount(4, $this->service->findAll(), 'Deve conter 4 registros showDeleted=false');

        // Apaga um registro "sem o cache saber"
        $this->service->getMapper()->getTableGateway()->delete("id=10");
        $this->service->getMapper()->setShowDeleted(true);
        $this->assertCount(5, $this->service->findAll(), 'Deve conter 5 registros');
        $this->assertTrue($this->service->getCache()->flush(), 'apaga o cache');
        $this->assertCount(4, $this->service->findAll(), 'Deve conter 4 registros 4');
    }

    /**
     * Tests Base->fetchRow()
     */
    public function testFindOne()
    {
        // Marca pra usar o campo deleted
        $this->service->getMapper()->setUseDeleted(true);

        $this->service->getMapper()->setOrder('id');

        // Verifica os itens que existem
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $this->service->findOne(1));
        $this->assertEquals($this->defaultValues[0], $this->service->findOne(1)->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $this->service->findOne(2));
        $this->assertEquals($this->defaultValues[1], $this->service->findOne(2)->toArray());
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $this->service->findOne(3));
        $this->assertEquals($this->defaultValues[2], $this->service->findOne(3)->toArray());
        $this->assertEmpty($this->service->findOne(4));

        // Verifica o item removido
        $this->service->getMapper()->setShowDeleted(true);
        $findOne = $this->service->findOne(4);
        $this->assertInstanceOf('\Realejo\Stdlib\ArrayObject', $findOne);
        $this->assertEquals($this->defaultValues[3], $findOne->toArray());
        $this->service->getMapper()->setShowDeleted(false);
    }

    /**
     * Tests Base->findAssoc()
     */
    public function testFindAssoc()
    {
        $this->service->getMapper()->setOrder('id');

        // O padrão é não usar o campo deleted
        $albuns = $this->service->findAssoc();
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
        $this->service->getMapper()->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->service->findAssoc(), 'showDeleted=true, useDeleted=false');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->service->getMapper()->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->service->findAssoc();
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

    /**
     * Tests Base->findAssoc()
     */
    public function testFindAssocWithMultipleKeys()
    {
        $this->service->getMapper()->setTableKey([$this->tableKeyName, 'naoexisto']);

        $this->service->getMapper()->setOrder('id');

        // O padrão é não usar o campo deleted
        $albuns = $this->service->findAssoc();
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
        $this->service->getMapper()->setShowDeleted(true)->setUseDeleted(false);
        $this->assertCount(4, $this->service->findAssoc(), 'showDeleted=true, useDeleted=false');

        // Marca pra mostrar os removidos e usar o campo deleted
        $this->service->getMapper()->setShowDeleted(true)->setUseDeleted(true);
        $albuns = $this->service->findAssoc();
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

    public function testHtmlSelectGettersSetters()
    {
        $this->assertEquals('{nome}', $this->service->getHtmlSelectOption(), 'padrão {nome}');
        $this->assertInstanceOf('\Realejo\Service\ServiceAbstract', $this->service->setHtmlSelectOption('{title}'),
            'setHtmlSelectOption() retorna RW_App_Model_Base');
        $this->assertEquals('{title}', $this->service->getHtmlSelectOption(), 'troquei por {title}');
    }

    public function testHtmlSelectWhere()
    {
        $id = 'teste';
        $this->service->setHtmlSelectOption('{title}');

        $this->service->getMapper()->setOrder('id');

        $select = $this->service->getHtmlSelect($id, null, ['where' => ['artist' => 'Rush']]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(3, $options, " 3 opções encontradas");

        $this->assertEmpty($options->current()->nodeValue, "primeiro é vazio 1");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do primeiro é vazio 1");

        $options->next();
        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, "nome do segundo ok 1");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 1");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, "nome do terceiro ok 1");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 1");


        $select = $this->service->getHtmlSelect($id, 1, ['where' => ['artist' => 'Rush']]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(2, $options, " 2 opções encontradas");

        $this->assertNotEmpty($options->current()->nodeValue, "primeiro não é vazio 2");
        $this->assertNotEmpty($options->current()->getAttribute('value'), "o valor do primeiro não é vazio 2");

        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, "nome do segundo ok 2");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 2");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, "nome do terceiro ok 2");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 2");
    }

    public function testHtmlSelectSemOptionValido()
    {
        $id = 'teste';
        $this->service->getMapper()->setOrder('id');

        $select = $this->service->getHtmlSelect($id);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(5, $options, " 5 opções encontradas");

        $this->assertEmpty($options->current()->nodeValue, "primeiro é vazio 1");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do primeiro é vazio 1");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do segundo ok 1");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 1");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do terceiro ok 1");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 1");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do quarto ok 1");
        $this->assertEquals($this->defaultValues[2]['id'], $options->current()->getAttribute('value'),
            "valor do quarto ok 1");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do quinto ok 1");
        $this->assertEquals($this->defaultValues[3]['id'], $options->current()->getAttribute('value'),
            "valor do quinto ok 1");

        $select = $this->service->setHtmlSelectOption('{nao_existo}')->getHtmlSelect($id);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(5, $options, " 5 opções encontradas");

        $this->assertEmpty($options->current()->nodeValue, "primeiro é vazio 2");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do primeiro é vazio 2");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do segundo ok 2");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 2");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do terceiro ok 2");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 2");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do quarto ok 2");
        $this->assertEquals($this->defaultValues[2]['id'], $options->current()->getAttribute('value'),
            "valor do quarto ok 2");

        $options->next();
        $this->assertEmpty($options->current()->nodeValue, "nome do quinto ok 2");
        $this->assertEquals($this->defaultValues[3]['id'], $options->current()->getAttribute('value'),
            "valor do quinto ok 2");
    }

    public function testHtmlSelectOption()
    {
        $id = 'teste';
        $this->service->getMapper()->setOrder('id');

        $select = $this->service->setHtmlSelectOption('{artist}')->getHtmlSelect($id);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute("#$id"), "id #$id existe");
        $this->assertCount(1, $dom->execute("select[name=\"$id\"]"), "placeholder select[name=\"$id\"] encontrado");
        $options = $dom->execute("option");
        $this->assertCount(5, $options, " 5 opções encontradas");

        $this->assertEmpty($options->current()->nodeValue, "primeiro é vazio");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do primeiro é vazio");

        $options->next();
        $this->assertEquals($this->defaultValues[0]['artist'], $options->current()->nodeValue, "nome do segundo ok");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['artist'], $options->current()->nodeValue, "nome do terceiro ok");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok");

        $options->next();
        $this->assertEquals($this->defaultValues[2]['artist'], $options->current()->nodeValue, "nome do quarto ok");
        $this->assertEquals($this->defaultValues[2]['id'], $options->current()->getAttribute('value'),
            "valor do quarto ok");

        $options->next();
        $this->assertEquals($this->defaultValues[3]['artist'], $options->current()->nodeValue, "nome do quinto ok");
        $this->assertEquals($this->defaultValues[3]['id'], $options->current()->getAttribute('value'),
            "valor do quinto ok");
    }

    public function testHtmlSelectPlaceholder()
    {
        $ph = 'myplaceholder';
        $this->service->getMapper()->setOrder('id');
        $select = $this->service->getHtmlSelect('nome_usado', null, ['placeholder' => $ph]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute('#nome_usado'), 'id #nome_usado existe');
        $this->assertCount(1, $dom->execute("select[placeholder=\"$ph\"]"),
            "placeholder select[placeholder=\"$ph\"] encontrado");
        $options = $dom->execute("option");
        $this->assertCount(5, $options, " 5 opções encontradas");
        $this->assertEquals($ph, $options->current()->nodeValue, "placeholder é a primeira");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do placeholder é vazio");
    }

    public function testHtmlSelectShowEmpty()
    {
        $this->service->getMapper()->setOrder('id');
        $select = $this->service->getHtmlSelect('nome_usado');
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute('#nome_usado'), 'id #nome_usado existe');
        $this->assertCount(5, $dom->execute('option'), '5 opções existem');
        $this->assertEmpty($dom->execute('option')->current()->nodeValue, "a primeira é vazia");
        $this->assertEmpty($dom->execute('option')->current()->getAttribute('value'), "o valor da primeira é vazio");

        $select = $this->service->getHtmlSelect('nome_usado', 1);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute('#nome_usado'), 'id #nome_usado existe COM valor padrão');
        $this->assertCount(4, $dom->execute('option'), '4 opções existem COM valor padrão');

        $select = $this->service->getHtmlSelect('nome_usado', null, ['show-empty' => false]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute('#nome_usado'),
            'id #nome_usado existe SEM valor padrão e show-empty=false');
        $this->assertCount(4, $dom->execute('option'), '4 opções existem SEM valor padrão e show-empty=false');

        // sem mostrar o empty
        $select = $this->service->getHtmlSelect('nome_usado', 1, ['show-empty' => false]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute('#nome_usado'),
            'id #nome_usado existe com valor padrão e show-empty=false');
        $this->assertCount(4, $dom->execute('option'), '4 opções existem com valor padrão e show-empty=false');

        // sem mostrar o empty
        $select = $this->service->getHtmlSelect('nome_usado', 1, ['show-empty' => true]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute('#nome_usado'), 'id #nome_usado existe com valor padrão e show-empty=true');
        $this->assertCount(5, $dom->execute('option'), '5 opções existem com valor padrão e show-empty=true');
        $this->assertEmpty($dom->execute('option')->current()->nodeValue,
            "a primeira é vazia com valor padrão e show-empty=true");
        $this->assertEmpty($dom->execute('option')->current()->getAttribute('value'),
            "o valor da primeira é vazio com valor padrão e show-empty=true");
    }

    public function testHtmlSelectGrouped()
    {
        $id = 'teste';
        $this->service->getMapper()->setOrder('id');

        $select = $this->service->setHtmlSelectOption('{title}')->getHtmlSelect($id, 1, ['grouped' => 'artist']);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute("#$id"), "id #$id existe");

        $options = $dom->execute("option");
        $this->assertCount(4, $options, " 4 opções encontradas");

        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, "nome do primeiro ok 1");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do primeiro ok 1");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, "nome do segundo ok 1");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 1");

        $options->next();
        $this->assertEquals($this->defaultValues[2]['title'], $options->current()->nodeValue, "nome do terceiro ok 1");
        $this->assertEquals($this->defaultValues[2]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 1");

        $options->next();
        $this->assertEquals($this->defaultValues[3]['title'], $options->current()->nodeValue, "nome do quarto ok 1");
        $this->assertEquals($this->defaultValues[3]['id'], $options->current()->getAttribute('value'),
            "valor do quarto ok 1");

        $optgroups = $dom->execute("optgroup");
        $this->assertCount(3, $optgroups, " 3 grupo de opções encontrados");

        $this->assertEquals($this->defaultValues[0]['artist'], $optgroups->current()->getAttribute('label'),
            "nome do primeiro grupo ok");
        $this->assertEquals(2, $optgroups->current()->childNodes->length, " 2 opções encontrados no priemiro optgroup");
        $this->assertEquals($this->defaultValues[0]['title'], $optgroups->current()->firstChild->nodeValue,
            "nome do primeiro ok 2");
        $this->assertEquals($this->defaultValues[0]['id'], $optgroups->current()->firstChild->getAttribute('value'),
            "valor do primeiro ok 2");
        $this->assertEquals($this->defaultValues[1]['title'], $optgroups->current()->firstChild->nextSibling->nodeValue,
            "nome do segundo ok 2");
        $this->assertEquals($this->defaultValues[1]['id'],
            $optgroups->current()->firstChild->nextSibling->getAttribute('value'), "valor do segundo ok 2");

        $optgroups->next();
        $this->assertEquals($this->defaultValues[2]['artist'], $optgroups->current()->getAttribute('label'),
            "nome do segundo grupo ok");
        $this->assertEquals(1, $optgroups->current()->childNodes->length, " 2 opções encontrados");
        $this->assertEquals($this->defaultValues[2]['title'], $optgroups->current()->firstChild->nodeValue,
            "nome do terceiro ok 2");
        $this->assertEquals($this->defaultValues[2]['id'], $optgroups->current()->firstChild->getAttribute('value'),
            "valor do terceiro ok 2");

        $optgroups->next();
        $this->assertEquals($this->defaultValues[3]['artist'], $optgroups->current()->getAttribute('label'),
            "nome do terceiro grupo ok");
        $this->assertEquals(1, $optgroups->current()->childNodes->length, " 2 opções encontrados");
        $this->assertEquals($this->defaultValues[3]['title'], $optgroups->current()->firstChild->nodeValue,
            "nome do terceiro ok 2");
        $this->assertEquals($this->defaultValues[3]['id'], $optgroups->current()->firstChild->getAttribute('value'),
            "valor do terceiro ok 2");

        // SELECT VAZIO!

        $select = $this->service->setHtmlSelectOption('{title}')->getHtmlSelect($id, 1,
            ['grouped' => 'artist', 'where' => ['id' => 100]]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);
        $this->assertCount(1, $dom->execute("#$id"), "id #$id existe");

        $this->assertCount(1, $dom->execute("option"), " nenhuma option com where id = 100");
        $this->assertCount(0, $dom->execute("optgroup"), " nenhuma optgroup com where id = 100");

        $this->assertEmpty($dom->execute("option")->current()->nodeValue, "primeiro é vazio");
        $this->assertEmpty($dom->execute("option")->current()->getAttribute('value'), "o valor do primeiro é vazio");
    }

    public function testHtmlSelectMultipleKey()
    {
        // Define a chave multipla
        // como ele deve considerar apenas o primeiro o teste abaixo é o mesmo de testHtmlSelectWhere
        $this->service->getMapper()->setTableKey(['id', 'nao-existo']);
        $this->service->getMapper()->setOrder('id');

        $id = 'teste';
        $this->service->setHtmlSelectOption('{title}');

        $select = $this->service->getHtmlSelect($id, null, ['where' => ['artist' => 'Rush']]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(3, $options, " 3 opções encontradas");

        $this->assertEmpty($options->current()->nodeValue, "primeiro é vazio 1");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do primeiro é vazio 1");

        $options->next();
        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, "nome do segundo ok 1");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 1");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, "nome do terceiro ok 1");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 1");


        $select = $this->service->getHtmlSelect($id, 1, ['where' => ['artist' => 'Rush']]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(2, $options, " 2 opções encontradas");

        $this->assertNotEmpty($options->current()->nodeValue, "primeiro não é vazio 2");
        $this->assertNotEmpty($options->current()->getAttribute('value'), "o valor do primeiro não é vazio 2");

        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, "nome do segundo ok 2");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 2");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, "nome do terceiro ok 2");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 2");
    }

    public function testHtmlSelectMultipleKeyWithCast()
    {
        // Define a chave multipla
        // como ele deve considerar apenas o primeiro o teste abaixo é o mesmo de testHtmlSelectWhere
        $this->service->getMapper()->setTableKey(['CAST' => 'id', 'nao-existo']);
        $this->service->getMapper()->setOrder('id');

        $id = 'teste';
        $this->service->setHtmlSelectOption('{title}');

        $select = $this->service->getHtmlSelect($id, null, ['where' => ['artist' => 'Rush']]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute("option");
        $this->assertCount(3, $options, " 3 opções encontradas");

        $this->assertEmpty($options->current()->nodeValue, "primeiro é vazio 1");
        $this->assertEmpty($options->current()->getAttribute('value'), "o valor do primeiro é vazio 1");

        $options->next();
        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, "nome do segundo ok 1");
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 1");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, "nome do terceiro ok 1");
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            "valor do terceiro ok 1");


        $select = $this->service->getHtmlSelect($id, 1, ['where' => ['artist' => 'Rush']]);
        $this->assertNotEmpty($select);
        $dom = new DomQuery($select);

        $options = $dom->execute('option');
        $this->assertCount(2, $options, ' 2 opções encontradas');

        $this->assertNotEmpty($options->current()->nodeValue, 'primeiro não é vazio 2');
        $this->assertNotEmpty($options->current()->getAttribute('value'), 'o valor do primeiro não é vazio 2');

        $this->assertEquals($this->defaultValues[0]['title'], $options->current()->nodeValue, 'nome do segundo ok 2');
        $this->assertEquals($this->defaultValues[0]['id'], $options->current()->getAttribute('value'),
            "valor do segundo ok 2");

        $options->next();
        $this->assertEquals($this->defaultValues[1]['title'], $options->current()->nodeValue, 'nome do terceiro ok 2');
        $this->assertEquals($this->defaultValues[1]['id'], $options->current()->getAttribute('value'),
            'valor do terceiro ok 2');
    }

    public function testServiceLocator()
    {
        $fakeServiceLocator = new FakeServiceLocator();
        $service = new ServiceConcrete();
        $service->setServiceLocator($fakeServiceLocator);
        $this->assertInstanceOf(FakeServiceLocator::class, $service->getServiceLocator());
        $this->assertInstanceOf(ContainerInterface::class, $service->getServiceLocator());

        $cacheService = new CacheService();
        $cacheService->setCacheDir($this->getDataDir() . '/cache');
        $service->setCache($cacheService->getFrontend());

        $mapper = $service->getMapper();
        $this->assertInstanceOf(MapperAbstract::class, $mapper);
        $this->assertInstanceOf(FakeServiceLocator::class, $mapper->getServiceLocator());
        $this->assertInstanceOf(ContainerInterface::class, $mapper->getServiceLocator());

        $this->assertNull($service->getFromServiceLocator('\DateTime'));

        $realServiceLocator = new ServiceManager();
        $service->setServiceLocator($realServiceLocator);
        $this->assertInstanceOf(\DateTime::class, $service->getFromServiceLocator('\DateTime'));

        $service->getFromServiceLocator(MetadataService::class);
        $this->assertTrue($service->getServiceLocator()->has(MetadataService::class));
        $this->assertInstanceOf(MetadataService::class, $service->getServiceLocator()->get(MetadataService::class));
        $this->assertTrue($service->getServiceLocator()->get(MetadataService::class)->hasServiceLocator());

        $fakeObject = (object)['id' => 1];
        $service->getServiceLocator()->setService('fake', $fakeObject);
        $this->assertTrue($service->getServiceLocator()->has('fake'));
        $this->assertEquals($fakeObject, $service->getServiceLocator()->get('fake'));
    }

    /**
     * Tests Base->findPaginated()
     */
    public function testFindPaginated()
    {
        $this->service->getMapper()->setOrder('id');
        $albuns = $this->service->findPaginated();
        $this->assertInstanceOf(\Realejo\Paginator\Paginator::class, $albuns);
        $this->assertCount(4, $albuns->getCurrentItems());

        $this->assertFalse($this->service->getUseCache());

        // Liga o cache
        $this->service->setUseCache(true);
        $this->assertTrue($this->service->getUseCache());

        // Verifica o paginator com o padrão
        $paginator = $this->service->findPaginated();

        // verifica se vai utilizar o mesmo cache id quando realizar a mesma consulta, pois estava criando novo id e nunca
        // utilizando o cache no paginator
        $oldId = $this->service->getCache()->getIterator()->key();
        $fetchAll = $this->service->setUseCache(true)->findPaginated();
        $this->assertEquals($oldId, $this->service->getCache()->getIterator()->key());
        // Apaga qualquer cache
        $this->assertTrue($this->service->getCache()->flush(), 'apaga o cache');

        $temp = [];
        foreach ($paginator->getIterator() as $p) {
            $temp[] = $p->getArrayCopy();
        }

        $findAll = $this->service->findAll();
        foreach ($findAll as $id => $row) {
            $findAll[$id] = $row->toArray();
        }
        $paginator = json_encode($temp);
        $this->assertEquals(json_encode($findAll), $paginator, 'retorno do paginator é igual');

        // Verifica o paginator alterando o paginator
        $this->service->getPaginatorOptions()
            ->setPageRange(2)
            ->setCurrentPageNumber(1)
            ->setItemCountPerPage(2);
        $paginator = $this->service->findPaginated();

        $temp = [];
        foreach ($paginator->getCurrentItems() as $p) {
            $temp[] = $p->getArrayCopy();
        }
        $paginator = json_encode($temp);

        $this->assertNotEquals(json_encode($this->defaultValues), $paginator);
        $fetchAll = $this->service->findPaginated(null, null, 2);
        $temp = [];
        foreach ($fetchAll as $p) {
            $temp[] = $p->toArray();
        }
        $fetchAll = $temp;
        $this->assertEquals(json_encode($fetchAll), $paginator);

        // verifica se vai utilizar o mesmo cache id quando realizar a mesma consulta, pois estava criando nova e nunca
        // utilizando o cache no paginator
        $oldId = $this->service->getCache()->getIterator()->key();
        $fetchAll = $this->service->setUseCache(true)->findPaginated(null, null, 2);
        $this->assertEquals($oldId, $this->service->getCache()->getIterator()->key());
    }
}
