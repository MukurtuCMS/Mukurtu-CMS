<?php

namespace Drupal\Tests\search_api\Unit\Views;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy\TermStorageInterface;

/**
 * Provides common methods for unit tests using taxonomy terms.
 *
 * @method \PHPUnit\Framework\MockObject\MockObject createMock(string $originalClassName)
 */
trait TaxonomyTestTrait {

  /**
   * The test container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The mock term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $termStorage;

  /**
   * The mock entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityRepository;

  /**
   * Sets up the container with necessary services.
   */
  public function setupContainer() {
    $this->container = new ContainerBuilder();
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $this->termStorage = $this->createMock(TermStorageInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->willReturnMap([
        ['taxonomy_term', $this->termStorage],
      ]);
    $this->container->set('entity.repository', $this->entityRepository);
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($this->container);
  }

}
