<?php

namespace Fixtures\Bundles\RepositoryServiceBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(repositoryClass="Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository")
 */
class TestCustomServiceRepoDocument
{
    /**
     * @ODM\Id
     */
    private $id;
}
