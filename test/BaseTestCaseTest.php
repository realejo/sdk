<?php

namespace RealejoTest;

use PHPUnit\Framework\TestCase;

/**
 * Test case para as funcionalidades com conexão ao banco de dados
 *
 * @link      http://github.com/realejo/libraray-zf2
 * @copyright Copyright (c) 2014 Realejo (http://realejo.com.br)
 * @license   http://unlicense.org
 */
class BaseTestCaseTest extends TestCase
{
    /**
     *
     * @var BaseTestCase
     */
    private $BaseTestCase;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();

        // TODO Auto-generated DbAdapterTest::setUp()

        $this->BaseTestCase = new BaseTestCase();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        // TODO Auto-generated DbAdapterTest::tearDown()
        $this->BaseTestCase = null;

        parent::tearDown();
    }

    /**
     * Tests DbAdapter->getAdapter()
     */
    public function testGetAdapter()
    {
        $this->assertInstanceOf('\Laminas\Db\Adapter\Adapter', $this->BaseTestCase->getAdapter());
    }

    /**
     * Tests DbAdapter->testSetupMysql()
     */
    public function testTestSetupMysql()
    {
        $tables = ['album'];
        $this->assertInstanceOf('\RealejoTest\BaseTestCase', $this->BaseTestCase->setTables($tables));
        $this->assertEquals($tables, $this->BaseTestCase->getTables());

        $dbTest = $this->BaseTestCase->createTables();
        $this->assertInstanceOf('\RealejoTest\BaseTestCase', $dbTest);

        $dbTest = $this->BaseTestCase->dropTables();
        $this->assertInstanceOf('\RealejoTest\BaseTestCase', $dbTest);

        $dbTest = $this->BaseTestCase->createTables()->dropTables();
        $this->assertInstanceOf('\RealejoTest\BaseTestCase', $dbTest);
    }

    public function testClearApplicationData()
    {
        // Verifica se está tudo ok
        if (!defined('TEST_DATA')) {
            $this->fail('TEST_DATA não definido');
        }
        if (!is_writable(TEST_DATA)) {
            $this->fail('TEST_DATA não tem permissão de escrita');
        }

        // Grava umas bobeiras la
        $folder = TEST_DATA . '/teste1';
        if (!file_exists($folder)) {
            $oldumask = umask(0);
            mkdir($folder);
            umask($oldumask);
        }
        file_put_contents($folder . '/test1.txt', 'teste');

        $folder = TEST_DATA . '/teste2/teste3';
        if (!file_exists($folder)) {
            $oldumask = umask(0);
            mkdir($folder, 0777, true);
            umask($oldumask);
        }
        file_put_contents($folder . '/sample.txt', 'teste teste');

        // Verifica se a pasta está vazia
        $this->assertFalse($this->BaseTestCase->isApplicationDataEmpty());

        $this->BaseTestCase->clearApplicationData();

        // Verifica se está vazia
        $files = $objects = scandir(TEST_DATA);
        $this->assertCount(3, $files, 'não tem mais nada no APPLICATION_DATA');
        $this->assertEquals(['.', '..', 'cache'], $files, 'não tem mais nada no APPLICATION_DATA');

        // Verifica se a pasta está vazia
        $this->assertTrue($this->BaseTestCase->isApplicationDataEmpty());

        // Grava mais coisa no raiz do APPLICATION_DATA
        file_put_contents(TEST_DATA . '/sample.txt', 'outro teste');

        // Verifica se a pasta está vazia depois de apagar
        $this->assertFalse($this->BaseTestCase->isApplicationDataEmpty());
        $this->assertTrue($this->BaseTestCase->clearApplicationData());
    }
}
