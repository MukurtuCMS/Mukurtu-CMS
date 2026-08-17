<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\mukurtu_local_contexts\Batch\LegacyLabelRemovalBatch;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for removing a specific legacy label/notice from an
 * admin-selected set of content.
 */
class LegacyLabelRemovalConfirmForm extends ConfirmFormBase {

  /**
   * The pending removal request loaded from tempstore.
   *
   * @var array|null
   */
  protected ?array $pending = NULL;

  /**
   * Constructs the form with dependencies.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private tempstore factory.
   */
  public function __construct(protected PrivateTempStoreFactory $tempStoreFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('tempstore.private'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_legacy_label_removal_confirm';
  }

  /**
   * Route access callback.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if (!$account->hasPermission('administer local contexts legacy projects')) {
      return AccessResult::forbidden();
    }
    $pending = $this->tempStoreFactory->get('mukurtu_local_contexts.label_removal')->get($account->id());
    return AccessResult::allowedIf(!empty($pending['node_ids']));
  }

  /**
   * Route title callback.
   */
  public function title() {
    return $this->getQuestion();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->pending = $this->tempStoreFactory->get('mukurtu_local_contexts.label_removal')->get($this->currentUser()->id());

    $form = parent::buildForm($form, $form_state);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $items = [];
    foreach ($storage->loadMultiple($this->pending['node_ids']) as $node) {
      $items[] = [
        '#type' => 'link',
        '#title' => $node->label(),
        '#url' => $node->toUrl('canonical'),
      ];
    }

    $form['items'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Selected items'),
      '#items' => $items,
      '#weight' => -10,
    ];

    return $form;
  }

  /**
   * Gets the display name for the pending label/notice.
   */
  protected function getLabelName(): string {
    $project = new LocalContextsProject($this->pending['project_id'] ?? '');
    return $project->resolveLabelOrNoticeName($this->pending['ref_type'] ?? 'label', $this->pending['ref_id'] ?? '') ?? ($this->pending['ref_id'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->formatPlural(
      count($this->pending['node_ids'] ?? []),
      'Remove @label from 1 item?',
      'Remove @label from @count items?',
      ['@label' => $this->getLabelName()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This permanently removes this legacy label from the selected content. Other labels, notices, and projects on these items are not affected. This cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('mukurtu_local_contexts.legacy_project_label_breakdown', [
      'project_id' => $this->pending['project_id'] ?? '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pending = $this->pending ?? [];
    $projectId = $pending['project_id'] ?? '';
    $refType = $pending['ref_type'] ?? 'label';
    $refId = $pending['ref_id'] ?? '';
    $nodeIds = $pending['node_ids'] ?? [];

    // Re-check which of the selected nodes still actually reference this
    // label/notice - something else (a remap, another removal pass) could
    // have already resolved it in the window between review and confirm.
    $project = new LocalContextsProject($projectId);
    $stillReferencing = $project->getReferencingNodeIdsForRef($refType, $refId);
    $toProcess = array_values(array_intersect($nodeIds, $stillReferencing));

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Removing legacy label/notice from selected content…'))
      ->setProgressMessage('')
      ->addOperation([LegacyLabelRemovalBatch::class, 'run'], [$projectId, $refType, $refId, $toProcess])
      ->setFinishCallback([LegacyLabelRemovalBatch::class, 'finished']);
    batch_set($batch->toArray());

    $this->tempStoreFactory->get('mukurtu_local_contexts.label_removal')->delete($this->currentUser()->id());
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
