<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines a "custom value" property.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\CustomValue
 */
class CustomValueProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'value' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $configuration = $field->getConfiguration();

    $module_handler = \Drupal::moduleHandler();
    $token_installed = $module_handler->moduleExists('token');
    if ($token_installed && $module_handler->moduleExists('help')) {
      $description = $this->t('Use this field to set the data to be sent to the index. You can use <a href=":url">replacement tokens</a> depending on the type of item being indexed.', [':url' => Url::fromRoute('help.page', ['name' => 'token'])->toString()]);
    }
    else {
      $description = $this->t('Use this field to set the data to be sent to the index. You can use replacement tokens depending on the type of item being indexed.');
    }
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field value'),
      '#description' => $description,
      '#default_value' => $configuration['value'] ?? '',
    ];

    // Allow the user to browse available tokens.
    if ($token_installed) {
      // Attempt to show only relevant token types.
      $token_types = [];
      $datasources = $field->getDatasource() ? [$field->getDatasource()] : $field->getIndex()->getDatasources();
      foreach ($datasources as $datasource) {
        $entity_type_id = $datasource->getEntityTypeId();
        if ($entity_type_id) {
          $token_types[] = $entity_type_id;
        }
      }
      $form['tokens']['help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => $token_types ?: 'all',
        '#dialog' => TRUE,
      ];
    }

    return $form;
  }

}
