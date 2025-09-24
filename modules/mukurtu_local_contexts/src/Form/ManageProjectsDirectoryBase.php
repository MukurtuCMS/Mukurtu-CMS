<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides form to manage any contexts projects directory.
 *
 * Used as a base form to manage the site, any protocol, or any community.
 */
abstract class ManageProjectsDirectoryBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $group = $form_state->getTemporaryValue('group');
    $config = $form_state->getTemporaryValue('config');

    if ($group) {
      $group_description = $group->get('field_local_contexts_description');
      $description_text = $this->t("Enter the description for @group's Local Contexts project directory page.", ['@group' => $group->getName()]);
      $description_value = $group_description->value;
      $description_format = $group_description->format ?? 'basic_html';
    }
    else {
      $config_description = $config->get('site_projects_directory_description') ?? [];
      $description_text = $this->t('Shown on the site-wide <a href=":url">Local Contexts projects directory page</a> (only when at least one Local Contexts project has been added).', [
        ':url' => Url::fromRoute('mukurtu_local_contexts.site_project_directory')->toString(),
      ]);
      $description_value = $config_description['value'] ?? '';
      $description_format = $config_description['format'] ?? 'basic_html';
    }

    $allowed_formats = ['basic_html', 'full_html'];
    $form['description'] = [
      '#title' => $this->t('Description'),
      // @todo: The description text does not show up in the Gin theme.
      '#description' => $description_text,
      '#default_value' => $description_value,
      '#type' => 'text_format',
      '#format' => $description_format,
      '#allowed_formats' => $allowed_formats,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }
}
