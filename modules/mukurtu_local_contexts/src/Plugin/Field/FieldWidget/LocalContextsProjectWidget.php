<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'local_contexts_project' field widget.
 *
 * @FieldWidget(
 *   id = "local_contexts_project",
 *   label = @Translation("Local Contexts Project Widget"),
 *   field_types = {"local_contexts_project"},
 *   multiple_values = TRUE
 * )
 */
class LocalContextsProjectWidget extends OptionsWidgetBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  protected $localContextsProjectManager;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $currentUser, LocalContextsSupportedProjectManager $supportedProjectManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->currentUser = $currentUser;
    $this->localContextsProjectManager = $supportedProjectManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('current_user'), $container->get('mukurtu_local_contexts.supported_project_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $options = $this->getOptions($items->getEntity());
    $selected = $this->getSelectedOptions($items);

    $element += [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $selected,
    ];

    // Tag each checkbox with its project id so JS can correlate a checked
    // project with its label/notice group in the sibling label widget,
    // regardless of how Drupal sanitizes the option key into an HTML id.
    foreach (array_keys($options) as $project_id) {
      $element[$project_id]['#attributes']['data-project-id'] = $project_id;
    }

    return $element;
  }

}
