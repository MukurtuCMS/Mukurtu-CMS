<?php

namespace Drupal\config_pages;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides rest module permissions.
 */
class ConfigPagesPermissions implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config_pages config storage.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $storage;

  /**
   * Constructs a new ConfigPagesPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->storage = $entity_type_manager->getStorage('config_pages_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of Config pages permissions.
   *
   * @return array
   *   Permissions.
   */
  public function permissions() {
    $permissions = [];
    $config_pages = $this->storage->loadMultiple();
    foreach ($config_pages as $config_page) {
      $permissions['view ' . $config_page->id() . ' config page entity'] = [
        'title' => $this->t('View the @label config page entity', ['@label' => $config_page->label()]),
      ];
      $permissions['edit ' . $config_page->id() . ' config page entity'] = [
        'title' => $this->t('Edit the @label config page entity', ['@label' => $config_page->label()]),
      ];
    }
    return $permissions;
  }

}
