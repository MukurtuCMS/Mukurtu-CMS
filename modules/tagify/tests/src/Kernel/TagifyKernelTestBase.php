<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for Tagify kernel tests.
 */
class TagifyKernelTestBase extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'user',
    'system',
    'taxonomy',
    'tagify',
  ];

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected FieldTypePluginManager $fieldTypeManager;

  /**
   * The cache backend interface for discovery cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cacheDiscovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $this->fieldTypeManager = $this->container->get('plugin.manager.field.field_type');
    $this->cacheDiscovery = $this->container->get('cache.discovery');
  }

}
