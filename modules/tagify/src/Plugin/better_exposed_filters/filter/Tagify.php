<?php

declare(strict_types=1);

namespace Drupal\tagify\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tagify widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_tagify",
 *   label = @Translation("Tagify"),
 * )
 */
class Tagify extends FilterWidgetBase {

  /**
   * The entity_autocomplete key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $request = $container->get('request_stack')->getCurrentRequest();
    $configFactory = $container->get('config.factory');
    $instance = new static($configuration, $plugin_id, $plugin_definition, $request, $configFactory);
    $instance->keyValue = $container->get('keyvalue')->get('entity_autocomplete');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    if (is_a($filter, 'Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid')) {
      // Autocomplete and dropdown taxonomy filter are both instances of
      // TaxonomyIndexTid, but we can't show BEF options for the select
      // widget.
      if ($filter_options['type'] == 'select') {
        return FALSE;
      }
    }

    return parent::isApplicable($filter, $filter_options);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['advanced']['match_operator'] = 'CONTAINS';
    $config['advanced']['max_items'] = 10;
    $config['advanced']['placeholder'] = '';

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    // Fail gracefully if the required properties ar not set.
    if (!isset($form[$field_id]['#target_type']) || !isset($form[$field_id]['#tags'])) {
      return;
    }

    $form[$field_id] = [
      '#type' => 'entity_autocomplete_tagify',
      '#target_type' => $form[$field_id]['#target_type'],
      '#tags' => $form[$field_id]['#tags'],
      '#selection_handler' => $form[$field_id]['#selection_handler'] ?? 'default',
      '#selection_settings' => $form[$field_id]['#selection_settings'] ?? [],
      '#match_operator' => $this->configuration['advanced']['match_operator'],
      '#max_items' => (int) $this->configuration['advanced']['max_items'],
      '#placeholder' => $this->configuration['advanced']['placeholder'],
      '#attributes' => [
        'class' => [$field_id],
      ],
      '#element_validate' => [[$this, 'elementValidate']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['advanced']['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $this->configuration['advanced']['match_operator'],
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
    ];

    $form['advanced']['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $this->configuration['advanced']['max_items'],
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];

    $form['advanced']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#description' => $this->t('Text to be shown in the Tagify field until a value is selected.'),
      '#default_value' => $this->configuration['advanced']['placeholder'],
    ];

    // Unset default placeholder text option.
    unset($form['advanced']['placeholder_text']);

    return $form;
  }

  /**
   * Validates and processes the autocomplete element values.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @throws \JsonException
   */
  public static function elementValidate(array $element, FormStateInterface $form_state): void {
    $value = $form_state->getValue($element['#parents']);
    if ($value && ($items = json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR))) {
      $formatted_items = self::formattedItems($items);
      if (!empty($formatted_items)) {
        $form_state->setValue($element['#parents'], $formatted_items);
      }
    }
  }

  /**
   * Formats filter items.
   *
   * @param array $items
   *   The filter items.
   *
   * @return array
   *   The formatted filter items.
   */
  protected static function formattedItems(array $items): array {
    foreach ($items as $item) {
      $formatted_items[] = [
        'target_id' => $item['entity_id'],
      ];
    }

    return $formatted_items ?? [];
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions() {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

}
