<?php

namespace Drupal\config_pages;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityListBuilderInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines a class to build a listing of custom config_pages entities.
 *
 * @see \Drupal\config_pages\Entity\ConfigPages
 */
class ConfigPagesListBuilder extends EntityListBuilder implements EntityListBuilderInterface, EntityHandlerInterface {

  /**
   * The ConfigPages storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $cptStorage;

  /**
   * The ConfigPages storage.
   *
   * @var \Drupal\config_pages\ConfigPagesContextManagerInterface
   */
  protected $cpContext;

  /**
   * The instantiated account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ) {

    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('config_pages_type'),
      $container->get('plugin.manager.config_pages_context'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    EntityStorageInterface $cpt_storage,
    ConfigPagesContextManagerInterface $cp_context,
    AccountProxyInterface $account,
  ) {
    parent::__construct($entity_type, $storage);
    $this->cptStorage = $cpt_storage;
    $this->cpContext = $cp_context;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = t('Name');
    $header['context'] = t('Context');
    $header['token'] = t('Exposed as tokens');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();

    // Used context.
    $contextData = [];
    if (!empty($entity->context['group'])) {
      foreach ($entity->context['group'] as $context_id => $context_enabled) {
        if ($context_enabled) {
          $item = $this->cpContext->getDefinition($context_id);
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
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if (isset($operations['edit'])) {
      $operations['edit']['query']['destination'] = $entity->toUrl('collection');
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {

    $operations = [];
    $account = $this->account;
    $edit_permission = ($account->hasPermission('edit config_pages entity')
      || $account->hasPermission('edit ' . $entity->id() . ' config page entity'));

    if (!$edit_permission) {
      return $operations;
    }

    // Use user entry path if available for edit/add form page.
    $path = $entity->menu['path'];
    if (!empty($path)) {
      $operations['edit'] = [
        'title' => t('Edit'),
        'weight' => 10,
        'query' => [],
        'url' => Url::fromUserInput($path),
      ];
    }
    else {
      // Use default config page path in another case.
      $operations['edit'] = [
        'title' => t('Edit'),
        'weight' => 10,
        'query' => [],
        'url' => Url::fromRoute('config_pages.add_form', ['config_pages_type' => $entity->id()]),
      ];
    }
    uasort($operations, '\Drupal\Component\Utility\SortArray::sortByWeightElement');

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_ids = $this->getEntityIds();
    return $this->cptStorage->loadMultiple($entity_ids);
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->cptStorage->getQuery();
    $keys = $this->entityType->getKeys();
    return $query
      ->accessCheck()
      ->sort($keys['id'])
      ->pager($this->limit)
      ->execute();
  }

}
