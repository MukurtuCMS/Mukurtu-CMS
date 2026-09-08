<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for the "Add to export list" bulk action on /admin/people.
 *
 * AddToExportListUserAction redirects here (via confirm_form_route_name)
 * before its executeMultiple() would otherwise run. This form reads the
 * staged user IDs directly from tempstore, lets the user pick or create an
 * export list, adds the users, then clears the tempstore.
 */
class ExportListAddUsersForm extends FormBase {

  /**
   * Constructs an ExportListAddUsersForm object.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_export_add_users_to_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uids = $this->tempStoreFactory->get('mukurtu_export.add_users_to_list')->get('uids');

    if (empty($uids)) {
      $this->messenger()->addWarning($this->t('No users are staged for export.'));
      $form_state->setRedirect('entity.export_list.collection');
      return $form;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    $form['uids'] = ['#type' => 'value', '#value' => $uids];

    $names = array_map(fn($user) => $user->getDisplayName(), $users);
    $form['list'] = [
      '#theme' => 'item_list',
      '#items' => $names,
      '#title' => $this->formatPlural(count($names), '1 user selected', '@count users selected'),
    ];

    // Export list selector, matching ExportListAddItemsForm's picker.
    $uid = $this->currentUser()->id();
    $storage = $this->entityTypeManager->getStorage('export_list');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $or = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    $list_ids = $query->condition($or)->sort('label')->execute();
    $lists = $storage->loadMultiple($list_ids);

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
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('entity.export_list.collection'),
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
      $error = $this->t('Select an export list or enter a name for a new one.');
      $form_state->setErrorByName('export_list_id', $error);
      $form_state->setErrorByName('new_list_name', $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (!empty($new_name)) {
      $list = $this->entityTypeManager->getStorage('export_list')->create([
        'label' => $new_name,
        'uid' => $this->currentUser()->id(),
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

    $uids = $form_state->getValue('uids', []);
    $list->addItems('user', $uids)->save();

    $this->messenger()->addStatus($this->formatPlural(
      count($uids),
      '1 user added to export list %label.',
      '@count users added to export list %label.',
      ['%label' => $list->label()],
    ));

    $this->tempStoreFactory->get('mukurtu_export.add_users_to_list')->delete('uids');
    $form_state->setRedirect('entity.export_list.collection');
  }

}
