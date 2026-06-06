<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for the "Add to export list" bulk flag action.
 *
 * When a user applies the export flag action from /admin/content (or
 * /admin/content/media), the form_alter submit handler redirects here.
 * This form reads all entities the current user has flagged with
 * export_content or export_media, lets them pick or create an export list,
 * then adds the entities and clears the flags.
 */
class ExportListAddItemsForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FlagServiceInterface $flagService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('flag'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_export_add_items_to_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = $this->currentUser();
    $staged = $this->getStagedEntities($account->id());

    if (empty($staged)) {
      $this->messenger()->addWarning($this->t('No items are staged for export.'));
      $destination = $this->getRequest()->query->get('destination');
      $destination
        ? $form_state->setRedirectUrl(Url::fromUserInput($destination))
        : $form_state->setRedirect('entity.export_list.collection');
      return $form;
    }

    // Build an item list of entity labels.
    $items = [];
    foreach ($staged as $entity_type => $ids) {
      $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
      foreach ($entities as $entity) {
        $items[] = $entity->label();
      }
    }

    $form['entities'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Selected items (@count)', ['@count' => count($items)]),
      '#items' => $items,
    ];

    // Export list selector.
    $uid = $account->id();
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
      '#title' => $this->t('Add to export list'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select export list -'),
      '#required' => FALSE,
    ];

    $form['new_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Or create a new list'),
      '#description' => $this->t('If provided, a new list will be created with this name.'),
      '#maxlength' => 255,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to List'),
      '#button_type' => 'primary',
    ];
    $destination = $this->getRequest()->query->get('destination');
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $destination ? Url::fromUserInput($destination) : Url::fromRoute('entity.export_list.collection'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (empty($new_name) && empty($form_state->getValue('export_list_id'))) {
      $form_state->setErrorByName('export_list_id', $this->t('Select an export list or enter a name for a new one.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $this->currentUser();
    $staged = $this->getStagedEntities($account->id());

    // Resolve or create the export list.
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (!empty($new_name)) {
      $list = $this->entityTypeManager->getStorage('export_list')->create([
        'label' => $new_name,
        'uid' => $account->id(),
        'site_wide' => FALSE,
      ]);
      $list->save();
    }
    else {
      $list = $this->entityTypeManager->getStorage('export_list')
        ->load($form_state->getValue('export_list_id'));
    }

    if (!$list) {
      $this->messenger()->addError($this->t('Could not find or create the export list.'));
      return;
    }

    // Add staged entities to the export list (read-modify-write).
    $items = $list->getItems();
    foreach ($staged as $entity_type => $ids) {
      $items[$entity_type] = $items[$entity_type] ?? [];
      foreach ($ids as $id) {
        $items[$entity_type][$id] = $id;
      }
    }
    $list->setItems($items)->save();

    // Unflag all staged entities so they're cleared from the staging area.
    $flag_map = [
      'node' => 'export_content',
      'media' => 'export_media',
    ];
    foreach ($staged as $entity_type => $ids) {
      $flag_id = $flag_map[$entity_type] ?? NULL;
      if (!$flag_id) {
        continue;
      }
      $flag = $this->flagService->getFlagById($flag_id);
      if (!$flag) {
        continue;
      }
      $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
      foreach ($entities as $entity) {
        try {
          $this->flagService->unflag($flag, $entity, $account);
        }
        catch (\Exception $e) {
          // Entity may not have been flagged; ignore.
        }
      }
    }

    $this->messenger()->addStatus($this->t('Items added to export list %label.', ['%label' => $list->label()]));
    $destination = $this->getRequest()->query->get('destination');
    $destination
      ? $form_state->setRedirectUrl(Url::fromUserInput($destination))
      : $form_state->setRedirect('entity.export_list.collection');
  }

  /**
   * Returns entity IDs flagged for export by the given user, keyed by type.
   *
   * @return array
   *   ['node' => [id => id, ...], 'media' => [...]]
   */
  protected function getStagedEntities(int $uid): array {
    $flag_map = [
      'node' => 'export_content',
      'media' => 'export_media',
    ];

    $staged = [];
    $db = \Drupal::database();
    foreach ($flag_map as $entity_type => $flag_id) {
      $result = $db->query(
        'SELECT entity_id FROM {flagging} WHERE uid = :uid AND flag_id = :flag_id',
        [':uid' => $uid, ':flag_id' => $flag_id]
      )->fetchCol();
      if (!empty($result)) {
        $staged[$entity_type] = array_combine($result, $result);
      }
    }
    return $staged;
  }

}
