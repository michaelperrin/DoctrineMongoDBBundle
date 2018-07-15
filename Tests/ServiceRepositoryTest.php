<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\MongoDBBundle\Tests;

use Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Doctrine\Bundle\MongoDBBundle\DependencyInjection\DoctrineMongoDBExtension;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Document\TestDefaultRepoDocument;
use Fixtures\Bundles\RepositoryServiceBundle\Document\TestCustomClassRepoDocument;
use Fixtures\Bundles\RepositoryServiceBundle\Document\TestCustomServiceRepoDocument;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomClassRepoRepository;
use Fixtures\Bundles\RepositoryServiceBundle\Repository\TestCustomServiceRepoRepository;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Fixtures\Bundles\RepositoryServiceBundle\RepositoryServiceBundle;

class ServiceRepositoryTest extends TestCase
{
    public function testRepositoryServiceWiring()
    {
        require_once __DIR__.'/DependencyInjection/Fixtures/Bundles/RepositoryServiceBundle/RepositoryServiceBundle.php';

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug'       => false,
            'kernel.bundles'     => ['RepositoryServiceBundle' => RepositoryServiceBundle::class],
            'kernel.cache_dir'   => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.name'        => 'kernel',
            'kernel.root_dir'    => __DIR__.'/../', // src dir
        ]));

        $container->setDefinition('annotation_reader', new Definition(AnnotationReader::class));
        $extension = new DoctrineMongoDBExtension();

        $extension->load([
            [
                'document_managers' => [
                    'default' => [
                        'mappings' => [
                            'RepositoryServiceBundle' => [
                                'type' => 'annotation',
                                'dir' => __DIR__.'/DependencyInjection/Fixtures/Bundles/RepositoryServiceBundle/Document',
                                'prefix' => 'Fixtures\Bundles\RepositoryServiceBundle\Document',
                            ]
                        ],
                    ]
                ],
                'connections' => [
                    'default' => []
                ],
            ],
        ], $container);

        $container->registerExtension($extension);

        $def = $container->register(TestCustomServiceRepoRepository::class, TestCustomServiceRepoRepository::class)
            ->setPublic(false);

        // Symfony 2.7 compat - can be moved above later
        if (method_exists($def, 'setAutowired')) {
            $def->setAutowired(true);
        }

        // Symfony 3.3 and higher: autowire definition so it receives the tags
        if (class_exists(ServiceLocatorTagPass::class)) {
            $def->setAutoconfigured(true);
        }

        $container->addCompilerPass(new ServiceRepositoryCompilerPass());
        $container->compile();

        $dm = $container->get('doctrine_mongodb.odm.default_document_manager');

        // traditional custom class repository
        $customClassRepo = $dm->getRepository(TestCustomClassRepoDocument::class);
        $this->assertInstanceOf(TestCustomClassRepoRepository::class, $customClassRepo);
        // a smoke test, trying some methods
        $this->assertSame(TestCustomClassRepoDocument::class, $customClassRepo->getClassName());
        $this->assertInstanceOf(Builder::class, $customClassRepo->createQueryBuilder('tc'));

        // generic DocumentRepository
        $genericRepository = $dm->getRepository(TestDefaultRepoDocument::class);
        $this->assertInstanceOf(DocumentRepository::class, $genericRepository);
        $this->assertSame($genericRepository, $genericRepository = $dm->getRepository(TestDefaultRepoDocument::class));
        // a smoke test, trying one of the methods
        $this->assertSame(TestDefaultRepoDocument::class, $genericRepository->getClassName());

        // Symfony 3.2 and lower should work normally in traditional cases (tested above)
        // the code below should *not* work (by design)
        if (!class_exists(ServiceLocatorTagPass::class)) {
            $message = '/Support for loading entities from the service container only works for Symfony 3\.3/';
            if (method_exists($this, 'expectException')) {
                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessageRegExp($message);
            } else {
                // PHPUnit 4 compat
                $this->setExpectedException(\RuntimeException::class);
                $this->setExpectedExceptionMessage($message);
            }
        }

        // custom service repository
        $customServiceRepo = $dm->getRepository(TestCustomServiceRepoDocument::class);
        $this->assertSame($customServiceRepo, $container->get(TestCustomServiceRepoRepository::class));
        // a smoke test, trying some methods
        $this->assertSame(TestCustomServiceRepoDocument::class, $customServiceRepo->getClassName());
        $this->assertInstanceOf(Builder::class, $customServiceRepo->createQueryBuilder('tc'));
    }
}
