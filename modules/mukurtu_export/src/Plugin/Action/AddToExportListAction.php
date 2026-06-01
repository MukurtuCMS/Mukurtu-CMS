<?php

namespace Drupal\mukurtu_export\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Adds content to a named export list.
 *
 * @Action(
 *   id = "mukurtu_export_add_to_list_action",
 *   label = @Translation("Add to export list"),
 *   type = "node",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = FALSE,
 *   },
 * )
 */
class AddToExportListAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  use StringTranslationTrait;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $account && $account->hasPermission('access mukurtu export')
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $storage = $this->entityTypeManager->getStorage('export_list');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $or = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    $ids = $query->condition($or)->sort('label')->execute();
    $lists = $storage->loadMultiple($ids);

    $options = [];
    foreach ($lists as $list) {
      $options[$list->id()] = $list->label();
    }

    $form['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select export list'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select export list -'),
      '#required' => TRUE,
    ];

    $form['new_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Or create a new list'),
      '#description' => $this->t('If provided, a new list will be created with this name.'),
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    $list_id = $form_state->getValue('export_list_id');
    if (empty($new_name) && empty($list_id)) {
      $form_state->setErrorByName('export_list_id', $this->t('Select an export list or enter a name for a new one.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (!empty($new_name)) {
      $list = $this->entityTypeManager->getStorage('export_list')->create([
        'label' => $new_name,
        'uid' => \Drupal::currentUser()->id(),
        'site_wide' => FALSE,
      ]);
      $list->save();
      $this->configuration['export_list_id'] = $list->id();
    }
    else {
      $this->configuration['export_list_id'] = $form_state->getValue('export_list_id');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $storage = $this->entityTypeManager->getStorage('export_list');
    $list = $storage->load($this->configuration['export_list_id']);
    if (!$list) {
      return;
    }

    $items = $list->getItems();
    foreach ($entities as $entity) {
      $entity_type = $entity->getEntityTypeId();
      $items[$entity_type] = $items[$entity_type] ?? [];
      $items[$entity_type][$entity->id()] = $entity->id();
    }
    $list->setItems($items);
    $list->save();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->executeMultiple([$entity]);
  }

}
