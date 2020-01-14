<?php

namespace RealejoTest\Sdk\Db;

use DateTime;
use RealejoTest\Sdk\Enum\EnumConcrete;
use RealejoTest\Sdk\Enum\EnumFlaggedConcrete;
use stdClass;

class HydratorSampleClass
{
    /**
     * @var bool
     */
    protected $booleanKey = false;

    /**
     * @var int
     */
    protected $intKey;

    /**
     * @var string
     */
    protected $stringKey;

    /**
     * @var array
     */
    protected $jsonArrayKey;

    /**
     * @var stdClass
     */
    protected $jsonObjectKey;

    /**
     * @var DateTime
     */
    protected $datetimeKey;

    /**
     * @var DateTime
     */
    protected $dateKey;

    /**
     * @var EnumConcrete
     */
    protected $enum;

    /**
     * @var EnumFlaggedConcrete
     */
    protected $enumFlagged;

    public function isBooleanKey(): bool
    {
        return $this->booleanKey;
    }

    public function setBooleanKey(bool $booleanKey): void
    {
        $this->booleanKey = $booleanKey;
    }

    public function getIntKey(): int
    {
        return $this->intKey;
    }

    public function setIntKey(int $intKey): void
    {
        $this->intKey = $intKey;
    }

    public function getJsonArrayKey(): array
    {
        return $this->jsonArrayKey;
    }

    public function setJsonArrayKey(array $jsonArrayKey): void
    {
        $this->jsonArrayKey = $jsonArrayKey;
    }

    public function getJsonObjectKey(): stdClass
    {
        return $this->jsonObjectKey;
    }

    public function setJsonObjectKey(stdClass $jsonObjectKey): void
    {
        $this->jsonObjectKey = $jsonObjectKey;
    }

    public function getDatetimeKey(): DateTime
    {
        return $this->datetimeKey;
    }

    public function setDatetimeKey(DateTime $datetimeKey): void
    {
        $this->datetimeKey = $datetimeKey;
    }

    public function getEnum(): EnumConcrete
    {
        return $this->enum;
    }

    public function setEnum(EnumConcrete $enum): void
    {
        $this->enum = $enum;
    }

    public function getEnumFlagged(): EnumFlaggedConcrete
    {
        return $this->enumFlagged;
    }

    public function setEnumFlagged(EnumFlaggedConcrete $enumFlagged): void
    {
        $this->enumFlagged = $enumFlagged;
    }

    public function getStringKey(): string
    {
        return $this->stringKey;
    }

    public function setStringKey(string $stringKey): void
    {
        $this->stringKey = $stringKey;
    }

    public function getDateKey(): DateTime
    {
        return $this->dateKey;
    }

    public function setDateKey(DateTime $dateKey): void
    {
        $this->dateKey = $dateKey;
    }
}
