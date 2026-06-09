<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for removing a single node from an export list.
 */
class ExportListRemoveNodeForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'mukurtu_export_remove_node_from_list';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->set('node', $node);

    $options = $this->getListOptions($node->id());

    if (empty($options)) {
      $this->messenger()->addWarning($this->t('%title is not in any export list.', ['%title' => $node->label()]));
      $form_state->setRedirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
      return $form;
    }

    $form['info'] = [
      '#markup' => $this->t('Remove <em>%title</em> from an export list.', ['%title' => $node->label()]),
    ];

    $form['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Remove from export list'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove from List'),
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

  protected function getListOptions(int $nid): array {
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
      $items = $list->getItems()['node'] ?? [];
      if (isset($items[$nid])) {
        $options[$list->id()] = $list->label();
      }
    }
    return $options;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->get('node');
    $list = $this->entityTypeManager->getStorage('export_list')
      ->load($form_state->getValue('export_list_id'));

    if (!$list) {
      $this->messenger()->addError($this->t('Could not find the export list.'));
      return;
    }

    $items = $list->getItems();
    unset($items['node'][$node->id()]);
    $list->setItems($items)->save();

    $this->messenger()->addStatus($this->t('%title removed from export list %label.', [
      '%title' => $node->label(),
      '%label' => $list->label(),
    ]));

    $form_state->setRedirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

}
