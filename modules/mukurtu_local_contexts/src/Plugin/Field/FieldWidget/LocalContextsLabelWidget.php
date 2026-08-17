<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'local_contexts_label_and_notice' field widget.
 *
 * @FieldWidget(
 *   id = "local_contexts_label_and_notice",
 *   label = @Translation("Local Contexts Label and Notice Widget"),
 *   field_types = {"local_contexts_label_and_notice"},
 *   multiple_values = TRUE
 * )
 */
class LocalContextsLabelWidget extends OptionsWidgetBase {

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
   *
   * Preserves the title-nested options structure from getOptions() so
   * formElement() can re-group it by project, instead of having it
   * flattened away before we ever see it.
   */
  protected function supportsGroups() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Renders one collapsible details group per project, each containing a
   * flat checkboxes element for just that project's labels/notices. A
   * single checkboxes element cannot be given a nested/grouped #options
   * array (Drupal core issue #2269823), so grouping is done in our own
   * markup instead of relying on core's optgroup handling.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // The inherited validator assumes a flat #value on this element, which
    // no longer applies to our nested details/checkboxes structure below.
    // massageFormValues() handles reshaping the submitted values instead.
    unset($element['#element_validate']);

    $nestedOptions = $this->getOptions($items->getEntity());
    $selected = $this->getSelectedOptions($items);

    // Re-group by the real project id parsed from each flat option key
    // ("{project_id}:{id}:{display}"), rather than trusting the title used
    // to key $nestedOptions, since two projects could share a title. This
    // mirrors the grouping LocalContextsLabelFormatter already does for
    // the display side.
    $groups = [];
    foreach ($nestedOptions as $title => $optionsForTitle) {
      foreach ($optionsForTitle as $key => $label) {
        [$project_id] = explode(':', $key, 2);
        $groups[$project_id]['title'] ??= $title;
        $groups[$project_id]['options'][$key] = $label;
      }
    }

    // #type is set to 'fieldset' (rather than left as a plain container) so
    // the field's own #title/#description, already set by WidgetBase::form()
    // before formElement() runs, render as a <legend> instead of being
    // silently dropped.
    $element['#type'] = 'fieldset';
    $element['#tree'] = TRUE;
    foreach ($groups as $project_id => $group) {
      $groupSelected = array_values(array_intersect($selected, array_keys($group['options'])));
      $element[$project_id] = [
        '#type' => 'details',
        '#title' => $group['title'],
        '#open' => !empty($groupSelected),
        '#attributes' => [
          'data-project-id' => $project_id,
          // Distinct from .local-contexts-label-group, which styles the
          // display-formatter's grid layout, not this form widget.
          'class' => ['local-contexts-label-widget__group'],
        ],
      ];
      $element[$project_id]['checkboxes'] = [
        '#type' => 'checkboxes',
        '#title' => $group['title'],
        // The details summary already shows the project title visually;
        // this restates it as the checkboxes group's accessible name so
        // screen reader users landing on an option (e.g. via a forms list)
        // still hear which project it belongs to, since <details>/<summary>
        // isn't announced as a group label the way a fieldset/legend is.
        '#title_display' => 'invisible',
        '#options' => $group['options'],
        '#default_value' => $groupSelected,
      ];
    }

    $element['#attached']['library'][] = 'mukurtu_local_contexts/local-contexts-label-widget';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $massaged = [];
    foreach ($values as $group) {
      if (!is_array($group) || empty($group['checkboxes'])) {
        continue;
      }
      foreach (array_filter($group['checkboxes']) as $key) {
        $massaged[] = [$this->column => $key];
      }
    }
    return $massaged;
  }

}
