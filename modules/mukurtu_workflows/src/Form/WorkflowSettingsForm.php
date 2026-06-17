<?php

namespace Drupal\mukurtu_workflows\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Settings form for switching the site-wide publishing workflow.
 */
class WorkflowSettingsForm extends FormBase {

  /**
   * Node bundles that Mukurtu manages via content moderation.
   */
  protected function getManagedBundles(): array {
    $types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
    return array_keys($types);
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
    $editorial = Workflow::load('mukurtu_editorial_workflow');
    $current_mode = 'simple';
    if ($editorial) {
      $type_settings = $editorial->get('type_settings');
      if (!empty($type_settings['entity_types']['node'])) {
        $current_mode = 'editorial';
      }
    }

    $form['workflow_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Publishing workflow'),
      '#default_value' => $current_mode,
      '#options' => [
        'simple' => $this->t('Simple -- authors can save drafts and publish content directly.'),
        'editorial' => $this->t('Editorial -- authors submit content for review; a Protocol Steward must approve it before it publishes.'),
      ],
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $mode = $form_state->getValue('workflow_mode');
    $bundles = $this->getManagedBundles();

    $editorial = Workflow::load('mukurtu_editorial_workflow');
    $default = Workflow::load('mukurtu_default_content_workflow');

    if (!$editorial || !$default) {
      $this->messenger()->addError($this->t('Could not load workflow configuration. Please check that the Mukurtu workflows module is properly installed.'));
      return;
    }

    $editorial_settings = $editorial->get('type_settings');
    $default_settings = $default->get('type_settings');

    if ($mode === 'editorial') {
      $editorial_settings['entity_types'] = ['node' => $bundles];
      $default_settings['entity_types'] = [];
    }
    else {
      $default_settings['entity_types'] = ['node' => $bundles];
      $editorial_settings['entity_types'] = [];
    }

    $editorial->set('type_settings', $editorial_settings);
    $editorial->save();
    $default->set('type_settings', $default_settings);
    $default->save();

    $this->messenger()->addStatus($this->t('Publishing workflow settings saved.'));
  }

}
