<?php

namespace Drupal\mukurtu_workflows\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for duplicating a workflow.
 */
class WorkflowDuplicateForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_workflow_duplicate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WorkflowInterface $workflow = NULL): array {
    if (!$workflow) {
      $this->messenger()->addError($this->t('Workflow not found.'));
      return $form;
    }

    $form_state->set('source_workflow_id', $workflow->id());

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->t('Copy of @label', ['@label' => $workflow->label()]),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#machine_name' => [
        'exists' => [Workflow::class, 'load'],
        'source' => ['label'],
      ],
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Duplicate'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('mukurtu_workflows.settings'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $source = Workflow::load($form_state->get('source_workflow_id'));

    if (!$source) {
      $this->messenger()->addError($this->t('Could not duplicate workflow.'));
      return;
    }

    $clone = $source->createDuplicate();
    $clone->set('id', $form_state->getValue('id'));
    $clone->set('label', $form_state->getValue('label'));

    // Clear entity_types so the duplicate starts unassigned.
    $type_settings = $clone->get('type_settings');
    $type_settings['entity_types'] = [];
    $clone->set('type_settings', $type_settings);

    $clone->save();

    $this->messenger()->addStatus($this->t('Workflow "@label" has been created.', ['@label' => $clone->label()]));
    $form_state->setRedirectUrl(Url::fromRoute('mukurtu_workflows.settings'));
  }

}
