<?php

namespace Realejo\Sdk\Db;

class PaginatorOptions
{

    /**
     * @var int
     */
    protected $pageRange = 10;

    /**
     * @var int
     */
    protected $currentPageNumber = 1;

    /**
     * @var int
     */
    protected $itemCountPerPage = 10;

    /**
     * @param int $pageRange
     * @return self
     */
    public function setPageRange(int $pageRange): self
    {
        $this->pageRange = $pageRange;
        return $this;
    }

    /**
     * @param int $currentPageNumber
     * @return self
     */
    public function setCurrentPageNumber(int $currentPageNumber): self
    {
        $this->currentPageNumber = $currentPageNumber;
        return $this;
    }

    /**
     * @param int $itemCountPerPage
     * @return self
     */
    public function setItemCountPerPage(int $itemCountPerPage): self
    {
        $this->itemCountPerPage = $itemCountPerPage;
        return $this;
    }

    public function getPageRange(): int
    {
        return $this->pageRange;
    }

    public function getCurrentPageNumber(): int
    {
        return $this->currentPageNumber;
    }

    public function getItemCountPerPage(): int
    {
        return $this->itemCountPerPage;
    }
}
