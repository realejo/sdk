<?php

namespace RealejoTest\Sdk\Test;

trait DataTrait
{
    protected $dataDir;

    protected function getDataDir(): string
    {
        if (empty($this->dataDir)) {
            // Verifica se há APPLICATION_DATA
            if (!defined('TEST_DATA')) {
                $this->fail('TEST_DATA not defined.');
            }
            $this->dataDir = TEST_DATA;
        }

        // Verifica se a pasta existe e tem permissão de escrita
        if (!is_dir($this->dataDir) || !is_writable($this->dataDir)) {
            $this->fail("{$this->dataDir} not writeable.");
        }

        return $this->dataDir;
    }

    protected function setDataDir(string $dataDir): void
    {
        $this->dataDir = $dataDir;
    }

    /**
     * Apaga todas pastas do APPLICATION_DATA
     * @return bool
     */
    protected function clearDataDir(): bool
    {
        // Apaga todo o conteudo dele
        $this->rrmdir($this->getDataDir(), $this->getDataDir());

        return $this->isDataDirEmpty();
    }

    /**
     * Retorna se a pasta APPLICATION_DATA está vazia
     * @return bool
     */
    protected function isDataDirEmpty(): bool
    {
        // Retorna se está vazio
        return (count(scandir($this->getDataDir())) === 3);
    }

    /**
     * Apaga recursivamente o conteudo de um pasta
     *
     * @param string $dir
     * @param string $root OPCIONAL pasta raiz para evitar que seja apagada
     */
    private function rrmdir(string $dir, string $root = null): void
    {
        // Não deixa executar em produção
        if (APPLICATION_ENV !== 'testing') {
            $this->fail('Só é possível executar rrmdir() em testing');
        }

        // Não deixa apagar fora do APPLICATION_DATA
        if (empty($this->getDataDir()) || strpos($dir, $this->getDataDir()) === false) {
            $this->fail('Não é possível apagar fora do APPLICATION_DATA');
        }

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..' && $object !== '.gitignore') {
                    if (filetype($dir . '/' . $object) === 'dir') {
                        $this->rrmdir($dir . '/' . $object, $root);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }

            if ($dir !== $root && count(scandir($dir)) === 2) {
                rmdir($dir);
            }
        }
    }
}
