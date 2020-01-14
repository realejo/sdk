<?php

namespace RealejoTest\Sdk\Db;

use ArrayObject;
use DateTime;
use PHPUnit\Framework\TestCase;
use RealejoTest\Sdk\Enum\EnumConcrete;
use RealejoTest\Sdk\Enum\EnumFlaggedConcrete;

/**
 * ArrayObject test case.
 */
class HydratorTest extends TestCase
{
    public function testHydrate(): void
    {
        $hydrator = new HydratorConcrete();

        $object = $hydrator->hydrate([], new ArrayObject());
        $this->assertEquals([], $hydrator->extract($object));

        $object = $hydrator->hydrate(
            ['one' => 'first', 'three' => 'áéíóú', 'four' => '\\slashes\\'],
            new ArrayObject()
        );
        $this->assertInstanceOf(ArrayObject::class, $object);
        $this->assertEquals([], $hydrator->extract($object), 'It only hydrate existing properties');

        $object = $hydrator->hydrate([], new HydratorSampleClass());
        $this->assertInstanceOf(HydratorSampleClass::class, $object);
        $this->assertEquals(
            [
                'boolean_key' => '0',
                'int_key' => null,
                'string_key' => null,
                'json_array_key' => null,
                'json_object_key' => null,
                'datetime_key' => null,
                'date_key' => null,
                'enum_flagged' => null,
                'enum' => null,
            ],
            $hydrator->extract($object)
        );

        $jsonArray = ['key' => 'value', 'unicode' => 'áéíóú😶çý', 'slashes' => '\\slashes\\'];
        $complete = [
            'boolean_key' => '1',
            'int_key' => '123',
            'string_key' => 'this is a test',
            'json_array_key' => json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'json_object_key' => json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'datetime_key' => '2019-05-23 15:46:12',
            'date_key' => '2019-05-23',
            'enum_flagged' => EnumFlaggedConcrete::WRITE,
            'enum' => EnumConcrete::STRING1,
        ];

        /** @var HydratorSampleClass $object */
        $object = $hydrator->hydrate($complete, new HydratorSampleClass());
        $this->assertInstanceOf(HydratorSampleClass::class, $object);
        $this->assertEquals($complete, $hydrator->extract($object));

        // check keys
        $this->assertTrue($object->isBooleanKey());

        $this->assertEquals(new DateTime('2019-05-23 15:46:12'), $object->getDatetimeKey());
        $this->assertEquals(new DateTime('2019-05-23 00:00:00'), $object->getDateKey());

        $this->assertEquals((int) $complete['int_key'], $object->getIntKey());

        $this->assertEquals($complete['string_key'], $object->getStringKey());

        $this->assertNotNull($object->getEnum());
        $this->assertEquals(EnumConcrete::STRING1, $object->getEnum()->getValue());
        $this->assertTrue($object->getEnum()->is(EnumConcrete::STRING1));

        $this->assertNotNull($object->getEnumFlagged());
        $this->assertEquals(EnumFlaggedConcrete::WRITE, $object->getEnumFlagged()->getValue());
        $this->assertTrue($object->getEnumFlagged()->is(EnumFlaggedConcrete::WRITE));

        $this->assertEquals($jsonArray, $object->getJsonArrayKey());
        $this->assertEquals((object)$jsonArray, $object->getJsonObjectKey());
    }
}
