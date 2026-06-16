<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for removing a single media item from an export list.
 */
class ExportListRemoveMediaForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'mukurtu_export_remove_media_from_list';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?MediaInterface $media = NULL): array {
    if ($media === NULL) {
      $this->messenger()->addError($this->t('Media item not found.'));
      $form_state->setRedirect('view.mukurtu_media.media_page_list');
      return $form;
    }

    $form_state->set('media', $media);

    $options = $this->getListOptions($media->id());

    if (empty($options)) {
      $this->messenger()->addWarning($this->t('%title is not in any export list.', ['%title' => $media->label()]));
      $form_state->setRedirect('view.mukurtu_media.media_page_list');
      return $form;
    }

    $form['info'] = [
      '#markup' => $this->t('Remove <em>%title</em> from an export list.', ['%title' => $media->label()]),
    ];

    $form['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Remove from export list'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select export list -'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove from List'),
      '#button_type' => 'primary',
    ];
    $cancel_url = $this->getReturnUrl();
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $cancel_url,
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  protected function getListOptions(int $mid): array {
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
      $items = $list->getItems()['media'] ?? [];
      if (isset($items[$mid])) {
        $options[$list->id()] = $list->label();
      }
    }
    return $options;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $media = $form_state->get('media');
    $list = $this->entityTypeManager->getStorage('export_list')
      ->load($form_state->getValue('export_list_id'));

    if (!$list) {
      $this->messenger()->addError($this->t('Could not find the export list.'));
      return;
    }

    $items = $list->getItems();
    unset($items['media'][$media->id()]);
    $list->setItems($items)->save();

    $this->messenger()->addStatus($this->t('%title removed from export list %label.', [
      '%title' => $media->label(),
      '%label' => $list->label(),
    ]));

    $form_state->setRedirectUrl($this->getReturnUrl());
  }

  protected function getReturnUrl(): Url {
    $destination = $this->requestStack()->getCurrentRequest()->query->get('destination');
    if ($destination && !str_starts_with($destination, 'http')) {
      return Url::fromUserInput($destination);
    }
    return Url::fromRoute('view.mukurtu_media.media_page_list');
  }

}
