<?php

namespace Drupal\mukurtu_workflows\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for managing site-wide publishing workflows.
 */
class WorkflowSettingsForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Node bundles that Mukurtu manages via content moderation.
   */
  protected function getManagedBundles(): array {
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    return array_keys($types);
  }

  /**
   * Returns the ID of the workflow currently assigned to node bundles.
   */
  protected function getActiveWorkflowId(): ?string {
    foreach (Workflow::loadMultiple() as $workflow) {
      $type_settings = $workflow->get('type_settings');
      if (!empty($type_settings['entity_types']['node'])) {
        return $workflow->id();
      }
    }
    return NULL;
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
        '#markup' => $workflow->label(),
      ];

      $form['workflow_table'][$id]['states'] = [
        '#markup' => implode(', ', $state_labels),
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
    $bundles = $this->getManagedBundles();

    foreach (Workflow::loadMultiple() as $id => $workflow) {
      $type_settings = $workflow->get('type_settings');
      $type_settings['entity_types'] = ($id === $active_id) ? ['node' => $bundles] : [];
      $workflow->set('type_settings', $type_settings);
      $workflow->save();
    }

    $this->messenger()->addStatus($this->t('Publishing workflow settings saved.'));
  }

}
