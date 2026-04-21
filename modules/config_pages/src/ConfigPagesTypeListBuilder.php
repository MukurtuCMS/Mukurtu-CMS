<?php

namespace Drupal\config_pages;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines a class to build a listing of custom config page type entities.
 *
 * @see \Drupal\config_pages\Entity\ConfigPagesType
 */
class ConfigPagesTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * Context manager.
   *
   * @var ConfigPagesContextManagerInterface
   */
  private $context;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.config_pages_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    ConfigPagesContextManagerInterface $context,
  ) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Place the edit operation after the operations added by field_ui.module
    // which have the weights 15, 20, 25.
    if (isset($operations['edit'])) {
      $operations['edit']['weight'] = 30;
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['type'] = t('Type');
    $header['context'] = t('Context');
    $header['token'] = t('Exposed as tokens');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['type'] = $entity->toLink(NULL, 'edit-form');

    // Used context.
    $contextData = [];
    if (!empty($entity->context['group'])) {
      foreach ($entity->context['group'] as $context_id => $context_enabled) {
        if ($context_enabled) {
          $item = $this->context->getDefinition($context_id);
          $context_value = $item['label'];
          $contextData[] = $context_value;
        }
      }
    }
    $row['context'] = implode(', ', $contextData);
    $row['token'] = !empty($entity->token)
      ? 'Exposed'
      : 'Hidden';

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTitle() {
    return $this->t('Config page types');
  }

}
