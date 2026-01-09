<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_dictionary\Form;

use Collator;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Configuration form for dictionary glossary order.
 */
class MukurtuDictionaryGlossaryOrderForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_dictionary_glossary_order.settings';

  /**
   * Constructs a MukurtuDictionaryGlossaryOrderForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_dictionary_glossary_order_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * Get all unique glossary entry values from dictionary_word nodes.
   *
   * @return array
   *   Array of unique glossary entry values.
   */
  protected function getGlossaryEntries(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery();
    $query->condition('type', 'dictionary_word')
      ->accessCheck(FALSE);
    $nids = $query->execute();

    $glossary_entries = [];
    if (!empty($nids)) {
      $nodes = $storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        if ($node->hasField('field_glossary_entry') && !$node->get('field_glossary_entry')->isEmpty()) {
          $value = $node->get('field_glossary_entry')->value;
          if (!empty($value)) {
            $glossary_entries[$value] = $value;
          }
        }
      }
    }

    return $glossary_entries;
  }

  /**
   * Sort an array using locale-aware collation.
   *
   * This uses PHP's Collator class to perform case-insensitive, locale-aware
   * sorting that properly handles accented characters and natural number order.
   * Collator accepts ISO 639 language codes and automatically uses appropriate
   * locale-specific sorting rules.
   *
   * @param array &$array
   *   The array to sort (passed by reference).
   */
  protected function sortByLocale(array &$array): void {
    // Get the current language code from Drupal.
    $current_language = $this->languageManager->getCurrentLanguage();
    $langcode = $current_language->getId();

    // Create a Collator instance using the language code.
    // Collator accepts ISO 639 codes ('en', 'es', 'fr', etc.) and automatically
    // applies appropriate locale-specific sorting rules.
    $collator = new Collator($langcode);

    // Sort the array using the collator for locale-aware comparison.
    uasort($array, function ($a, $b) use ($collator) {
      return $collator->compare($a, $b);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::SETTINGS);

    $form['helper'] = [
      '#type' => 'item',
      '#markup' => $this->t('Choose how glossary entries should be sorted in the dictionary facet. You can use the default unicode (alphabetical) order, or define a custom order by dragging entries into your preferred sequence.'),
    ];

    // Sort mode selection.
    $form['sort_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sort mode'),
      '#options' => [
        'default' => $this->t('Default sort (unicode/alphabetical order)'),
        'custom' => $this->t('User-defined sort (drag to reorder below)'),
      ],
      '#default_value' => $config->get('sort_mode') ?? 'default',
      '#required' => TRUE,
    ];

    // Get all glossary entries.
    $glossary_entries = $this->getGlossaryEntries();

    if (empty($glossary_entries)) {
      $form['no_entries'] = [
        '#type' => 'item',
        '#markup' => $this->t('No glossary entries found. Create some dictionary words first.'),
      ];
      return parent::buildForm($form, $form_state);
    }

    // Get existing weights from config.
    $saved_weights = $config->get('weights') ?? [];
    $weights = [];
    foreach ($saved_weights as $item) {
      if (isset($item['glossary_entry']) && isset($item['weight'])) {
        $weights[$item['glossary_entry']] = $item['weight'];
      }
    }

    // Build weighted entries array, using unicode sort for new entries.
    $weighted_entries = [];
    $max_weight = !empty($weights) ? max($weights) + 1 : 0;

    // First, add all entries with existing weights.
    $dupes = [];
    foreach ($glossary_entries as $entry) {
      if (isset($weights[$entry])) {
        $weight = $weights[$entry];
        // Handle duplicate weights.
        if (isset($weighted_entries[(string) $weight])) {
          $dupes[$weight] = ($dupes[$weight] ?? 0) + 1;
          $weight = $weight . '.' . $dupes[$weight];
        }
        else {
          $weight = (string) $weight;
        }
        $weighted_entries[$weight] = $entry;
      }
    }

    // Then, add new entries (not in weights) in alphabetical order.
    $new_entries = array_diff($glossary_entries, array_keys($weights));
    if (!empty($new_entries)) {
      // Sort new entries using locale-aware collation.
      $this->sortByLocale($new_entries);
      foreach ($new_entries as $entry) {
        $weighted_entries[(string) $max_weight] = $entry;
        $max_weight++;
      }
    }

    // Sort by weight key.
    ksort($weighted_entries);

    // Build the draggable table.
    $form['table-row'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Glossary Entry'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('There are no glossary entries.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="sort_mode"]' => ['value' => 'custom'],
        ],
      ],
    ];

    // Build the table rows.
    foreach ($weighted_entries as $index => $entry) {
      $entry_weight = $weights[$entry] ?? (int) $index;
      $form['table-row'][$entry]['#attributes']['class'][] = 'draggable';
      $form['table-row'][$entry]['#weight'] = $entry_weight;
      $form['table-row'][$entry]['name'] = [
        '#markup' => $entry,
      ];
      $form['table-row'][$entry]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', [
          '@title' => $entry,
        ]),
        '#title_display' => 'invisible',
        '#default_value' => $entry_weight,
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
    ];
    $form['actions']['reset_to_alphabetical'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Order to Alphabetical'),
      '#submit' => [
        '::resetToAlphabetical',
      ],
      '#limit_validation_errors' => [],
      '#states' => [
        'visible' => [
          ':input[name="sort_mode"]' => ['value' => 'custom'],
        ],
      ],
      '#attributes' => [
        'title' => $this->t('Reset all glossary entry weights to alphabetical order'),
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => 'Cancel',
      '#attributes' => [
        'title' => $this->t('Return to the dashboard'),
      ],
      '#submit' => [
        '::cancel',
      ],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * Form submission handler for the 'Reset Order to Alphabetical' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetToAlphabetical(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(static::SETTINGS);

    // Get all current glossary entries.
    $glossary_entries = $this->getGlossaryEntries();

    // Sort entries using locale-aware collation.
    $this->sortByLocale($glossary_entries);

    // Build the weights array with sequential weights.
    $weights = [];
    $weight = 0;
    foreach ($glossary_entries as $entry) {
      $weights[] = [
        'glossary_entry' => $entry,
        'weight' => $weight,
      ];
      $weight++;
    }

    // Save the weights array to config.
    $config->set('weights', $weights);
    $config->save();

    // Rebuild the form to show the new weights.
    $form_state->setRebuild(TRUE);

    // Give the user a success message.
    $this->messenger()->addStatus($this->t('Glossary entry weights have been reset to alphabetical order.'));
  }

  /**
   * Form submission handler for the 'Cancel' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancel(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('mukurtu_core.dashboard');
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(static::SETTINGS);

    // Save the sort mode.
    $sort_mode = $form_state->getValue('sort_mode');
    $config->set('sort_mode', $sort_mode);

    // Build the weights array from the form.
    $weights = [];
    $submission = $form_state->getValue('table-row');
    if ($submission) {
      foreach ($submission as $entry => $item) {
        $weights[] = [
          'glossary_entry' => $entry,
          'weight' => $item['weight'],
        ];
      }
    }

    // Save the weights array to config.
    $config->set('weights', $weights);
    $config->save();

    // Give the user a success message.
    $this->messenger()->addStatus($this->t('The dictionary glossary order configuration has been saved.'));
  }

}
