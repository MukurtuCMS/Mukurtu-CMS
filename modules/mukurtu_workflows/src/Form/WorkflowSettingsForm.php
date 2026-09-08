<?php

namespace Drupal\mukurtu_workflows\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for managing site-wide publishing workflows.
 */
class WorkflowSettingsForm extends FormBase {

  /**
   * Node bundles that are never subject to content moderation.
   */
  protected const EXCLUDED_BUNDLES = ['landing_page'];

  protected const DEFAULT_WORKFLOW_ID = 'mukurtu_default_content_workflow';

  protected const EDITORIAL_WORKFLOW_ID = 'mukurtu_editorial_workflow';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $workflowConfigFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * Node bundles under Mukurtu's cultural protocol / OG access gating.
   */
  protected function getProtocolGatedBundles(): array {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $bundles = array_keys($this->entityTypeManager->getStorage('node_type')->loadMultiple());
    $gated = [];
    foreach ($bundles as $bundle) {
      if (in_array($bundle, self::EXCLUDED_BUNDLES, TRUE)) {
        continue;
      }
      $class = $node_storage->getEntityClass($bundle);
      if ($class && in_array(CulturalProtocolControlledInterface::class, class_implements($class), TRUE)) {
        $gated[] = $bundle;
      }
    }
    return $gated;
  }

  /**
   * Node bundles that always use the Default workflow.
   *
   * These are structural, non-protocol-gated bundles (e.g. article, page).
   */
  protected function getStructuralBundles(): array {
    $bundles = array_keys($this->entityTypeManager->getStorage('node_type')->loadMultiple());
    $excluded = array_merge(self::EXCLUDED_BUNDLES, $this->getProtocolGatedBundles());
    return array_values(array_diff($bundles, $excluded));
  }

  /**
   * Returns the ID of the workflow currently selected for protocol-gated content.
   */
  protected function getActiveWorkflowId(): ?string {
    return $this->workflowConfigFactory->get('mukurtu_workflows.settings')->get('active_workflow');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_workflow_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $active_id = $this->getActiveWorkflowId();
    $workflows = Workflow::loadMultiple();

    $form['protocol_gated_description'] = [
      '#markup' => '<p>' . $this->t("The workflow selected as active below controls Mukurtu's protocol-controlled content (collections, dictionary words, digital heritage, people, and places). Articles and pages always use the Default Content Workflow.") . '</p>',
    ];

    $form['workflow_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Active'),
        $this->t('Workflow'),
        $this->t('States'),
        $this->t('Actions'),
      ],
      '#empty' => $this->t('No workflows found.'),
    ];

    foreach ($workflows as $id => $workflow) {
      $is_mukurtu = str_starts_with($id, 'mukurtu_');
      $states = $workflow->getTypePlugin()->getStates();
      $state_labels = array_map(fn($s) => $s->label(), $states);

      $form['workflow_table'][$id]['active'] = [
        '#type' => 'radio',
        '#title' => $this->t('Set @label as active', ['@label' => $workflow->label()]),
        '#title_display' => 'invisible',
        '#return_value' => $id,
        '#default_value' => $active_id,
        '#parents' => ['active_workflow'],
      ];

      $form['workflow_table'][$id]['label'] = [
        '#plain_text' => $workflow->label(),
      ];

      $form['workflow_table'][$id]['states'] = [
        '#plain_text' => implode(', ', $state_labels),
      ];

      $links = [
        'duplicate' => [
          'title' => $this->t('Duplicate'),
          'url' => Url::fromRoute('mukurtu_workflows.duplicate', ['workflow' => $id]),
        ],
      ];
      if (!$is_mukurtu) {
        $links['edit'] = [
          'title' => $this->t('Edit'),
          'url' => $workflow->toUrl('edit-form'),
        ];
        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => $workflow->toUrl('delete-form'),
        ];
      }

      $form['workflow_table'][$id]['actions'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    $form['add_workflow'] = [
      '#type' => 'link',
      '#title' => $this->t('Create new workflow'),
      '#url' => Url::fromRoute('entity.workflow.add_form'),
      '#attributes' => ['class' => ['button', 'button--action', 'button--primary']],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('active_workflow')) {
      $form_state->setError($form['workflow_table'], $this->t('Please select an active workflow.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $active_id = $form_state->getValue('active_workflow');

    $this->workflowConfigFactory->getEditable('mukurtu_workflows.settings')
      ->set('active_workflow', $active_id)
      ->save();

    $structural = $this->getStructuralBundles();
    $protocol_gated = $this->getProtocolGatedBundles();

    foreach (Workflow::loadMultiple() as $id => $workflow) {
      if ($id === self::DEFAULT_WORKFLOW_ID) {
        $bundles = $active_id === self::EDITORIAL_WORKFLOW_ID ? $structural : array_merge($structural, $protocol_gated);
      }
      elseif ($id === self::EDITORIAL_WORKFLOW_ID) {
        $bundles = $active_id === self::EDITORIAL_WORKFLOW_ID ? $protocol_gated : [];
      }
      else {
        // Custom/duplicated workflows are not managed by this form.
        continue;
      }

      $type_settings = $workflow->get('type_settings');
      $type_settings['entity_types'] = $bundles ? ['node' => $bundles] : [];
      $workflow->set('type_settings', $type_settings);
      $workflow->save();
    }

    $this->messenger()->addStatus($this->t('Publishing workflow settings saved.'));
  }

}
