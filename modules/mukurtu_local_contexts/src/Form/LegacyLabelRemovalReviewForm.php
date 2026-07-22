<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets an admin review, one item at a time, which content should have a
 * specific unmapped legacy label/notice removed from it.
 */
class LegacyLabelRemovalReviewForm extends FormBase {

  /**
   * Constructs the form with dependencies.
   *
   * @param \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager $supportedProjectManager
   *   The Local Contexts supported project manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private tempstore factory.
   */
  public function __construct(
    protected LocalContextsSupportedProjectManager $supportedProjectManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_local_contexts.supported_project_manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_legacy_label_removal_review';
  }

  /**
   * Route access callback.
   */
  public function access(AccountInterface $account, string $project_id, string $ref_type): AccessResultInterface {
    if (!$account->hasPermission('administer local contexts legacy projects')) {
      return AccessResult::forbidden();
    }
    if (!in_array($ref_type, ['label', 'notice'], TRUE)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIf($this->supportedProjectManager->isLegacyProjectId($project_id));
  }

  /**
   * Route title callback.
   */
  public function title(string $project_id, string $ref_type, string $ref_id) {
    $project = new LocalContextsProject($project_id);
    $name = $project->resolveLabelOrNoticeName($ref_type, $ref_id) ?? $ref_id;
    return $this->t('Review content referencing @label', ['@label' => $name]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $project_id = NULL, ?string $ref_type = NULL, ?string $ref_id = NULL) {
    $project = new LocalContextsProject($project_id);
    $nids = $project->getReferencingNodeIdsForRef($ref_type, $ref_id);

    $form['#tree'] = TRUE;
    $form_state->set('project_id', $project_id);
    $form_state->set('ref_type', $ref_type);
    $form_state->set('ref_id', $ref_id);

    $form['instructions'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Select the content items you want to remove this legacy label from. Only the items you check will be affected — everything else is left exactly as it is.'),
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $options = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      $options[$node->id()] = [
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $node->label(),
            '#url' => $node->toUrl('canonical'),
          ],
        ],
        'type' => $bundle_info[$node->bundle()]['label'] ?? $node->bundle(),
      ];
    }

    $form['items'] = [
      '#type' => 'tableselect',
      '#header' => [
        'title' => $this->t('Title'),
        'type' => $this->t('Content Type'),
      ],
      '#options' => $options,
      '#empty' => $this->t('No content currently references this label.'),
      '#js_select' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to Confirm'),
      '#button_type' => 'primary',
      '#gin_action_item' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected = array_keys(array_filter($form_state->getValue('items', [])));

    if (!$selected) {
      $this->messenger()->addError($this->t('Select at least one item to continue.'));
      return;
    }

    $this->tempStoreFactory->get('mukurtu_local_contexts.label_removal')->set($this->currentUser()->id(), [
      'project_id' => $form_state->get('project_id'),
      'ref_type' => $form_state->get('ref_type'),
      'ref_id' => $form_state->get('ref_id'),
      'node_ids' => array_map('intval', $selected),
    ]);

    $form_state->setRedirect('mukurtu_local_contexts.legacy_label_removal_confirm');
  }

}
