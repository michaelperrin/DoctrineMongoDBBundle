<?php

namespace Doctrine\Bundle\MongoDBBundle\Tests\Repository;

use Doctrine\Bundle\MongoDBBundle\Repository\ContainerRepositoryFactory;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepositoryInterface;
use Doctrine\Bundle\MongoDBBundle\Tests\Fixtures\Foo\BoringDocument;
use Doctrine\Bundle\MongoDBBundle\Tests\Fixtures\Foo\CoolDocument;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager as BaseDocumentManager;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ContainerRepositoryFactoryTest extends TestCase
{
    public function testGetRepositoryReturnsService()
    {
        if (!interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $dm = $this->createTestDocumentManager([
            CoolDocument::class => 'my_repo',
        ]);

        $repo = new StubRepository($dm, $dm->getUnitOfWork(), new ClassMetadata(CoolDocument::class));
        $container = $this->createContainer([
            'my_repo' => $repo,
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $this->assertSame($repo, $factory->getRepository($dm, CoolDocument::class));
    }

    public function testGetRepositoryReturnsDocumentRepository()
    {
        if (!interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $container = $this->createContainer([]);
        $dm = $this->createTestDocumentManager([
            BoringDocument::class => null,
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $actualRepo = $factory->getRepository($dm, BoringDocument::class);
        $this->assertInstanceOf(DocumentRepository::class, $actualRepo);
        // test the same instance is returned
        $this->assertSame($actualRepo, $factory->getRepository($dm, BoringDocument::class));
    }

    public function testCustomRepositoryIsReturned()
    {
        if (!interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $container = $this->createContainer([]);
        $dm = $this->createTestDocumentManager([
            CoolDocument::class => StubRepository::class,
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $actualRepo = $factory->getRepository($dm, CoolDocument::class);
        $this->assertInstanceOf(StubRepository::class, $actualRepo);
        // test the same instance is returned
        $this->assertSame($actualRepo, $factory->getRepository($dm, CoolDocument::class));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The service "my_repo" must extend DocumentRepository (or a base class, like ServiceDocumentRepository).
     */
    public function testServiceRepositoriesMustExtendDocumentRepository()
    {
        if (!interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $repo = new \stdClass();

        $container = $this->createContainer([
            'my_repo' => $repo,
        ]);

        $dm = $this->createTestDocumentManager([
            CoolDocument::class => 'my_repo',
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($dm, CoolDocument::class);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "Doctrine\Bundle\MongoDBBundle\Tests\Repository\StubServiceRepository" document repository implements "Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepositoryInterface", but its service could not be found. Make sure the service exists and is tagged with "doctrine.repository_service".
     */
    public function testRepositoryMatchesServiceInterfaceButServiceNotFound()
    {
        if (!interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('Symfony 3.3 is needed for this feature.');
        }

        $container = $this->createContainer([]);

        $dm = $this->createTestDocumentManager([
            CoolDocument::class => StubServiceRepository::class,
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($dm, CoolDocument::class);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "Doctrine\Bundle\MongoDBBundle\Tests\Fixtures\Foo\CoolDocument" document has a repositoryClass set to "not_a_real_class", but this is not a valid class. Check your class naming. If this is meant to be a service id, make sure this service exists and is tagged with "doctrine.repository_service".
     */
    public function testCustomRepositoryIsNotAValidClass()
    {
        if (interface_exists(ContainerInterface::class)) {
            $container = $this->createContainer([]);
        } else {
            // Symfony 3.2 and lower support
            $container = null;
        }

        $dm = $this->createTestDocumentManager([
            CoolDocument::class => 'not_a_real_class',
        ]);

        $factory = new ContainerRepositoryFactory($container);
        $factory->getRepository($dm, CoolDocument::class);
    }

    private function createContainer(array $services)
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->any())
            ->method('has')
            ->willReturnCallback(function ($id) use ($services) {
                return isset($services[$id]);
            });
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($services) {
                return $services[$id];
            });

        return $container;
    }

    private function createTestDocumentManager(array $documentRepositoryClasses)
    {
        $config = new \Doctrine\ODM\MongoDB\Configuration();
        $config->setHydratorDir(\sys_get_temp_dir());
        $config->setHydratorNamespace('SymfonyTests\Doctrine');
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');

        $dm = DocumentManager::create(new Connection(), $config);

        foreach ($documentRepositoryClasses as $entityClass => $DocumentRepositoryClass) {
            $metadata = new ClassMetadata($entityClass);
            $metadata->customRepositoryClassName = $DocumentRepositoryClass;

            $dm->setClassMetadata($entityClass, $metadata);
        }

        return $dm;
    }
}

class StubRepository extends DocumentRepository
{
}

class StubServiceRepository extends DocumentRepository implements ServiceDocumentRepositoryInterface
{
}

class DocumentManager extends BaseDocumentManager {
    protected $classMetadatas = array();

    public function setClassMetadata($className, ClassMetadata $class)
    {
        $this->classMetadatas[$className] = $class;
    }

    public function getClassMetadata($className)
    {
        if ( ! isset($this->classMetadatas[$className])) {
            throw new \InvalidArgumentException('Metadata for class ' . $className . ' doesn\'t exist, try calling ->setClassMetadata() first');
        }
        return $this->classMetadatas[$className];
    }
}
