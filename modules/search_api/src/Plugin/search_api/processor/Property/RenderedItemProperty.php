<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;
use Drupal\search_api\Utility\Utility;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Defines a "rendered item" property.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\RenderedItem
 */
class RenderedItemProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'roles' => [AccountInterface::ANONYMOUS_ROLE],
      'view_mode' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $configuration = $field->getConfiguration();
    $index = $field->getIndex();
    $form['#tree'] = TRUE;

    $roles = array_map(function (RoleInterface $role) {
      return Utility::escapeHtml($role->label());
    }, Role::loadMultiple());
    $form['roles'] = [
      '#type' => 'select',
      '#title' => $this->t('User roles'),
      '#description' => $this->t('Your item will be rendered as seen by a user with the selected roles. We recommend to just use "@anonymous" here to prevent data leaking out to unauthorized roles.', ['@anonymous' => $roles[AccountInterface::ANONYMOUS_ROLE]]),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#default_value' => $configuration['roles'],
      '#required' => TRUE,
    ];

    $form['view_mode'] = [
      '#type' => 'item',
      '#description' => $this->t('You can choose the view modes to use for rendering the items of different datasources and bundles. We recommend using a dedicated view mode (for example, the "Search index" view mode available by default for content) to make sure that only relevant data (especially no field labels) will be included in the index.'),
    ];

    $options_present = FALSE;
    $bundle_options = [
      '' => $this->t("Don't include the rendered item."),
      ':default' => $this->t('Use the default setting.'),
    ];
    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $datasource_config = $configuration['view_mode'][$datasource_id] ?? [];
      $form['view_mode'][$datasource_id][':default'] = [
        '#type' => 'select',
        '#title' => $this->t('Default view mode for %datasource', ['%datasource' => $datasource->label()]),
        '#options' => ['' => $bundle_options['']] + $datasource->getViewModes(),
        '#default_value' => $datasource_config[':default'] ?? '',
        '#description' => $this->t('You can override this setting per bundle by choosing different view modes below.'),
      ];
      $bundles = $datasource->getBundles();
      foreach ($bundles as $bundle_id => $bundle_label) {
        $view_modes = $datasource->getViewModes($bundle_id);
        if ($view_modes) {
          $form['view_mode'][$datasource_id][$bundle_id] = [
            '#type' => 'select',
            '#title' => $this->t('View mode for %datasource » %bundle', [
              '%datasource' => $datasource->label(),
              '%bundle' => $bundle_label,
            ]),
            '#options' => $bundle_options + $view_modes,
            '#default_value' => $datasource_config[$bundle_id] ?? ':default',
          ];
          $options_present = TRUE;
        }
        else {
          $form['view_mode'][$datasource_id][$bundle_id] = [
            '#type' => 'value',
            '#value' => FALSE,
          ];
        }
      }
    }
    // If there are no datasources/bundles with more than one view mode, don't
    // display the description either.
    if (!$options_present) {
      unset($form['view_mode']['#type']);
      unset($form['view_mode']['#description']);
    }

    return $form;
  }

}
