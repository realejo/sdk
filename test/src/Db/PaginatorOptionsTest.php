<?php

namespace RealejoTest\Sdk\Db;

use PHPUnit\Framework\TestCase;
use Realejo\Sdk\Db\PaginatorOptions;

/**
 * PaginatorOptions test case.
 */
class PaginatorOptionsTest extends TestCase
{

    /**
     * Tests PaginatorOptions->setPageRange()
     */
    public function testGettersAndSetters():void
    {
        $paginator = new PaginatorOptions();

        $this->assertEquals(1, $paginator->getCurrentPageNumber());
        $this->assertInstanceOf(get_class($paginator), $paginator->setCurrentPageNumber(2));
        $this->assertEquals(2, $paginator->getCurrentPageNumber());

        $this->assertEquals(10, $paginator->getItemCountPerPage());
        $this->assertInstanceOf(get_class($paginator), $paginator->setItemCountPerPage(20));
        $this->assertEquals(20, $paginator->getItemCountPerPage());

        $this->assertEquals(10, $paginator->getPageRange());
        $this->assertInstanceOf(get_class($paginator), $paginator->setPageRange(30));
        $this->assertEquals(30, $paginator->getPageRange());
    }
}
