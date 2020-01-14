<?php

namespace RealejoTest\Sdk\Cache;

use PHPUnit\Framework\TestCase;
use Realejo\Sdk\Cache\CacheService;
use RealejoTest\Sdk\Test\DataTrait;
use RealejoTest\Sdk\Test\DbTrait;
use Laminas\Cache\Storage\Adapter\Filesystem;

class CacheTest extends TestCase
{
    use DataTrait;
    use DbTrait;

    /**
     * @var CacheService
     */
    protected $cacheService;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->cacheService = new CacheService();
        $this->cacheService->setCacheDir($this->getDataDir() . '/cache');

        $this->clearDataDir();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        parent::tearDown();

        // Remove as pastas criadas
        $this->clearDataDir();
    }

    /**
     * getCachePath sem nome da pasta
     */
    public function testGetCacheRoot(): void
    {
        // Recupera a pasta aonde será salva as informações
        $path = $this->cacheService->getCacheRoot();

        // Verifica se tere o retorno correto
        $this->assertNotNull($path);
        $this->assertEquals(realpath(TEST_DATA . '/cache'), $path);
        $this->assertFileExists($path);
        $this->assertDirectoryExists($path);
        $this->assertTrue(is_writable($path));
    }

    /**
     * getCachePath sem nome da pasta
     */
    public function testGetCachePath(): void
    {
        // Verifica se todas as opções são iguais
        $this->assertEquals($this->cacheService->getCacheRoot(), $this->cacheService->getCachePath(null));
        $this->assertEquals($this->cacheService->getCacheRoot(), $this->cacheService->getCachePath(''));
        $this->assertEquals($this->cacheService->getCacheRoot(), $this->cacheService->getCachePath());

        // Cria ou recupera a pasta album
        $path = $this->cacheService->getCachePath('Album');

        // Verifica se foi criada corretamente a pasta
        $this->assertNotNull($path);
        $this->assertEquals(realpath(TEST_DATA . '/cache/album'), $path);
        $this->assertNotEquals(realpath(TEST_DATA . '/cache/Album'), $path);
        $this->assertFileExists($path);
        $this->assertDirectoryExists($path);
        $this->assertTrue(is_writable($path));

        // Apaga a pasta
        $this->rrmdir($path);

        // Verifica se a pasta foi apagada
        $this->assertFileNotExists($path);

        // Cria ou recupera a pasta album
        $path = $this->cacheService->getCachePath('album');

        // Verifica se foi criada corretamente a pasta
        $this->assertNotNull($path);
        $this->assertEquals(realpath(TEST_DATA . '/cache/album'), $path);
        $this->assertNotEquals(realpath(TEST_DATA . '/cache/Album'), $path);
        $this->assertFileExists($path, 'Verifica se a pasta album existe');
        $this->assertDirectoryExists($path, 'Verifica se a pasta album é uma pasta');
        $this->assertTrue(is_writable($path), 'Verifica se a pasta album tem permissão de escrita');

        // Apaga a pasta
        $this->rrmdir($path);

        // Verifica se a pasta foi apagada
        $this->assertFileNotExists($path);

        // Cria ou recupera a pasta
        $path = $this->cacheService->getCachePath('album_Teste');

        // Verifica se foi criada corretamente a pasta
        $this->assertNotNull($path);
        $this->assertEquals(realpath(TEST_DATA . '/cache/album/teste'), $path);
        $this->assertNotEquals(realpath(TEST_DATA . '/cache/Album/Teste'), $path);
        $this->assertFileExists($path, 'Verifica se a pasta album_Teste existe');
        $this->assertDirectoryExists($path, 'Verifica se a pasta album_Teste é uma pasta');
        $this->assertTrue(is_writable($path), 'Verifica se a pasta album_Teste tem permissão de escrita');

        // Apaga a pasta
        $this->rrmdir($path);

        // Verifica se a pasta foi apagada
        $this->assertFileNotExists($path, 'Verifica se a pasta album_Teste foi apagada');

        // Cria ou recupera a pasta
        $path = $this->cacheService->getCachePath('album/Teste');

        // Verifica se foi criada corretamente a pasta
        $this->assertNotNull($path, 'Teste se o album/Teste foi criado');
        $this->assertEquals(realpath(TEST_DATA . '/cache/album/teste'), $path);
        $this->assertNotEquals(realpath(TEST_DATA . '/cache/Album/Teste'), $path);
        $this->assertFileExists($path, 'Verifica se a pasta album/Teste existe');
        $this->assertDirectoryExists($path, 'Verifica se a pasta album/Teste é uma pasta');
        $this->assertTrue(is_writable($path), 'Verifica se a pasta album/Teste tem permissão de escrita');
    }

    /**
     * getFrontend com nome da class
     */
    public function testGetFrontendComClass(): void
    {
        $cache = $this->cacheService->getFrontend('Album');
        $this->assertInstanceOf(Filesystem::class, $cache);
    }
}
