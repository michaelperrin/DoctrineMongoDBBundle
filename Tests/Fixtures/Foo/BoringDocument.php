<?php

namespace Doctrine\Bundle\MongoDBBundle\Tests\Fixtures\Foo;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** @ODM\Document */
class BoringDocument
{
    /** @ODM\Id(strategy="none") */
    protected $id;
}
