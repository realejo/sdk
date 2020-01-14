<?php

namespace RealejoTest\Sdk\Test;

use Laminas\Test\PHPUnit\Controller\AbstractControllerTestCase as LaminasAbstractControllerTestCase;

abstract class AbstractTestCase extends LaminasAbstractControllerTestCase
{
    use DbTrait;
    use CommonTrait;
}
