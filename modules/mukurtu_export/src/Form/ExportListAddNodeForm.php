<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for adding a single node to an export list.
 */
class ExportListAddNodeForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'mukurtu_export_add_node_to_list';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->set('node', $node);

    $form['info'] = [
      '#markup' => $this->t('Adding <em>%title</em> to an export list.', ['%title' => $node->label()]),
    ];

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
      '#url' => \Drupal\Core\Url::fromRoute('view.mukurtu_manage_all_content.mukurtu_manage_content'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $new_name = trim($form_state->getValue('new_list_name') ?? '');
    if (empty($new_name) && empty($form_state->getValue('export_list_id'))) {
      $form_state->setErrorByName('export_list_id', $this->t('Select an export list or enter a name for a new one.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->get('node');

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

    $items = $list->getItems();
    $items['node'][$node->id()] = $node->id();
    $list->setItems($items)->save();

    $this->messenger()->addStatus($this->t('%title added to export list %label.', [
      '%title' => $node->label(),
      '%label' => $list->label(),
    ]));

    $form_state->setRedirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

}
