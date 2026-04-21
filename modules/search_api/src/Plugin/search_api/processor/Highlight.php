<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use function implode;

/**
 * Adds a highlighted excerpt to results and highlights returned fields.
 *
 * This processor won't run for queries with the "basic" processing level set.
 */
#[SearchApiProcessor(
  id: 'highlight',
  label: new TranslatableMarkup('Highlight'),
  description: new TranslatableMarkup('Adds a highlighted excerpt to results and highlights returned fields.'),
  stages: [
    'pre_index_save' => 0,
    'postprocess_query' => 0,
  ],
)]
class Highlight extends ProcessorPluginBase implements PluginFormInterface {

  use LoggerTrait;
  use PluginFormTrait;

  /**
   * PCRE regular expression for a word boundary.
   *
   * We highlight around non-indexable or CJK characters.
   *
   * @var string
   */
  protected static $boundary;

  /**
   * PCRE regular expression for splitting words.
   *
   * We highlight around non-indexable or CJK characters.
   *
   * @var string
   */
  protected static $split;

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelperInterface|null
   */
  protected $dataTypeHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset(static::$boundary)) {
      // cspell:disable
      $cjk = '\x{1100}-\x{11FF}\x{3040}-\x{309F}\x{30A1}-\x{318E}' .
        '\x{31A0}-\x{31B7}\x{31F0}-\x{31FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FCF}' .
        '\x{A000}-\x{A48F}\x{A4D0}-\x{A4FD}\x{A960}-\x{A97F}\x{AC00}-\x{D7FF}' .
        '\x{F900}-\x{FAFF}\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}\x{FF66}-\x{FFDC}' .
        '\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}';
      // cspell:enable
      static::$boundary = '(?:(?<=[' . Unicode::PREG_CLASS_WORD_BOUNDARY . $cjk . '])|(?=[' . Unicode::PREG_CLASS_WORD_BOUNDARY . $cjk . ']))';
      static::$split = '/[' . Unicode::PREG_CLASS_WORD_BOUNDARY . ']+/iu';
    }
  }

  /**
   * Retrieves the data type helper.
   *
   * @return \Drupal\search_api\Utility\DataTypeHelperInterface
   *   The data type helper.
   */
  public function getDataTypeHelper() {
    return $this->dataTypeHelper ?: \Drupal::service('search_api.data_type_helper');
  }

  /**
   * Sets the data type helper.
   *
   * @param \Drupal\search_api\Utility\DataTypeHelperInterface $data_type_helper
   *   The new data type helper.
   *
   * @return $this
   */
  public function setDataTypeHelper(DataTypeHelperInterface $data_type_helper) {
    $this->dataTypeHelper = $data_type_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preIndexSave() {
    parent::preIndexSave();

    if (empty($this->configuration['exclude_fields'])) {
      return;
    }

    $renames = $this->index->getFieldRenames();

    $selected_fields = array_flip($this->configuration['exclude_fields']);
    $renames = array_intersect_key($renames, $selected_fields);
    if ($renames) {
      $new_fields = array_keys(array_diff_key($selected_fields, $renames));
      $new_fields = array_merge($new_fields, array_values($renames));
      $this->configuration['exclude_fields'] = $new_fields;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'prefix' => '<strong>',
      'suffix' => '</strong>',
      'excerpt' => TRUE,
      'excerpt_length' => 256,
      'excerpt_always' => FALSE,
      'highlight' => 'always',
      'highlight_partial' => FALSE,
      'exclude_fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $parent_name = 'processors[highlight][settings]';
    if (!empty($form['#parents'])) {
      $parents = $form['#parents'];
      $parent_name = $root = array_shift($parents);
      if ($parents) {
        $parent_name = $root . '[' . implode('][', $parents) . ']';
      }
    }

    $form['highlight'] = [
      '#type' => 'select',
      '#title' => $this->t('Highlight returned field data'),
      '#description' => $this->t('Select whether returned fields should be highlighted.'),
      '#options' => [
        'always' => $this->t('Always'),
        'server' => $this->t('If the server returns fields'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => $this->configuration['highlight'],
    ];
    $form['highlight_partial'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Highlight partial matches'),
      '#description' => $this->t('When enabled, matches in parts of words will be highlighted as well.'),
      '#default_value' => $this->configuration['highlight_partial'],
    ];
    $form['excerpt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create excerpt'),
      '#description' => $this->t('When enabled, an excerpt will be created for searches with keywords, containing all occurrences of keywords in a fulltext field.'),
      '#default_value' => $this->configuration['excerpt'],
    ];
    $form['excerpt_always'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create excerpt even if no search keys are available'),
      '#description' => $this->t('When enabled, an excerpt will be created even with an empty query string.'),
      '#default_value' => $this->configuration['excerpt_always'],
    ];
    $form['excerpt_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Excerpt length'),
      '#description' => $this->t('The requested length of the excerpt, in characters'),
      '#default_value' => $this->configuration['excerpt_length'],
      '#min' => 50,
      '#states' => [
        'visible' => [
          ":input[name=\"{$parent_name}[excerpt]\"]" => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
    // Exclude certain fulltext fields.
    $fields = $this->index->getFields();
    $fulltext_fields = [];
    foreach ($this->index->getFulltextFields() as $field_id) {
      $fulltext_fields[$field_id] = $fields[$field_id]->getLabel() . ' (' . $field_id . ')';
    }
    $form['exclude_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude fields from excerpt'),
      '#description' => $this->t('Exclude certain fulltext fields from being included in the excerpt.'),
      '#options' => $fulltext_fields,
      '#default_value' => $this->configuration['exclude_fields'],
      '#attributes' => ['class' => ['search-api-checkboxes-list']],
      '#states' => [
        'visible' => [
          ":input[name=\"{$parent_name}[excerpt]\"]" => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Highlighting prefix'),
      '#description' => $this->t('Text/HTML that will be prepended to all occurrences of search keywords in highlighted text'),
      '#default_value' => $this->configuration['prefix'],
    ];
    $form['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Highlighting suffix'),
      '#description' => $this->t('Text/HTML that will be appended to all occurrences of search keywords in highlighted text'),
      '#default_value' => $this->configuration['suffix'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Sanitize the storage for the "exclude_fields" setting.
    $excluded = &$form_state->getValue('exclude_fields');
    $excluded = array_keys(array_filter($excluded));
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    $query = $results->getQuery();
    if (!$results->getResultCount()
      || $query->getProcessingLevel() != QueryInterface::PROCESSING_FULL
      || $query->hasTag('search_api_skip_processor_highlight')) {
      return;
    }

    // Only return an excerpt on an empty keyword if requested by configuration.
    $keys = $this->getKeywords($query);
    $excerpt_always = $this->configuration['excerpt_always'];
    if (!$excerpt_always && !$keys) {
      return;
    }

    $excerpt_fulltext_fields = $this->index->getFulltextFields();
    if (!empty($this->configuration['exclude_fields'])) {
      $excerpt_fulltext_fields = array_diff($excerpt_fulltext_fields, $this->configuration['exclude_fields']);
    }

    $result_items = $results->getResultItems();
    if ($this->configuration['excerpt']) {
      $this->addExcerpts($result_items, $excerpt_fulltext_fields, $keys);
    }
    if ($this->configuration['highlight'] !== 'never' && !empty($keys)) {
      $highlighted_fields = $this->highlightFields($result_items, $keys);
      foreach ($highlighted_fields as $item_id => $item_fields) {
        $item = $result_items[$item_id];
        $item->setExtraData('highlighted_fields', $item_fields);
      }
    }
  }

  /**
   * Adds excerpts to all results, if possible.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $results
   *   The result items to which excerpts should be added.
   * @param string[] $fulltext_fields
   *   The fulltext fields from which the excerpt should be created.
   * @param array $keys
   *   The search keys to use for highlighting.
   */
  protected function addExcerpts(array $results, array $fulltext_fields, array $keys) {
    $items = $this->getFulltextFields($results, $fulltext_fields);
    $index = $this->getIndex();
    $indexed_fields = $index->getFields();
    foreach ($items as $item_id => $item) {
      if (!$item) {
        continue;
      }

      // Prepare structured field data instead of concatenating everything.
      $field_data = [];
      foreach ($item as $field_id => $values) {
        $indexed_field = $indexed_fields[$field_id] ?? NULL;
        $field_data[] = [
          'field_id' => $field_id,
          'values' => $values,
        ];
      }

      $item_keys = $keys;

      // If the backend already did highlighting and told us the exact keys it
      // found in the item's text values, we can use those for our own
      // highlighting. This will help us take stemming, transliteration, etc.
      // into account properly.
      $item_keys = $results[$item_id]->getExtraData('highlighted_keys') ?: $item_keys;

      $results[$item_id]->setExcerpt($this->createExcerptForFields($field_data, $item_keys));
    }
  }

  /**
   * Retrieves highlighted field values for the given result items.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $results
   *   The result items whose fields should be highlighted.
   * @param array $keys
   *   The search keys to use for highlighting.
   *
   * @return string[][][]
   *   An array keyed by item IDs, containing arrays that map field IDs to the
   *   highlighted versions of the values for that field.
   */
  protected function highlightFields(array $results, array $keys) {
    $highlighted_fields = [];
    foreach ($results as $item_id => $item) {
      // Maybe the backend or some other processor has already set highlighted
      // field values.
      $highlighted_fields[$item_id] = $item->getExtraData('highlighted_fields', []);
    }

    $load = $this->configuration['highlight'] == 'always';
    $item_fields = $this->getFulltextFields($results, NULL, $load);
    foreach ($item_fields as $item_id => $fields) {
      foreach ($fields as $field_id => $values) {
        if (empty($highlighted_fields[$item_id][$field_id])) {
          $change = FALSE;
          foreach ($values as $i => $value) {
            $values[$i] = $this->highlightField($value, $keys);
            if ($values[$i] !== $value) {
              $change = TRUE;
            }
          }
          if ($change) {
            $highlighted_fields[$item_id][$field_id] = $values;
          }
        }
      }
    }
    return $highlighted_fields;
  }

  /**
   * Retrieves the fulltext fields of the given result items.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $result_items
   *   The results for which fulltext data should be extracted, keyed by item
   *   ID.
   * @param string[]|null $fulltext_fields
   *   (optional) The fulltext fields to highlight, or NULL to highlight all
   *   fulltext fields.
   * @param bool $load
   *   (optional) If FALSE, only field values already present will be returned.
   *   Otherwise, fields will be loaded if necessary.
   *
   * @return mixed[][][]
   *   Field values extracted from the result items' fulltext fields, keyed by
   *   item ID, field ID and then numeric indices.
   */
  protected function getFulltextFields(array $result_items, ?array $fulltext_fields = NULL, $load = TRUE) {
    // All the index's fulltext fields, grouped by datasource.
    $fields_by_datasource = [];
    foreach ($this->index->getFields() as $field_id => $field) {
      if (isset($fulltext_fields) && !in_array($field_id, $fulltext_fields)) {
        continue;
      }

      // For configurable properties, append the field ID to be able to discern
      // multiple versions of the same property (e.g., multiple aggregated
      // fields, aggregating different base properties).
      $path = $field->getPropertyPath();
      try {
        $property = $field->getDataDefinition();
        if ($property instanceof ConfigurablePropertyInterface) {
          $path .= '|' . $field_id;
        }
      }
      catch (SearchApiException $e) {
        $this->logException($e);
      }

      if ($this->getDataTypeHelper()->isTextType($field->getType())) {
        $fields_by_datasource[$field->getDatasourceId()][$path] = $field_id;
      }
    }

    return $this->getFieldsHelper()
      ->extractItemValues($result_items, $fields_by_datasource, $load);
  }

  /**
   * Filters out empty values from an array while preserving keys.
   *
   * @param array $values
   *   The input array that may contain empty values.
   *
   * @return array
   *   The filtered array with empty values removed. Keys are preserved, and any
   *   non-empty nested arrays are processed recursively.
   */
  protected function filterEmptyValuesPreserveKeys(array $values): array {
    $filtered = [];

    foreach ($values as $key => $value) {
      if (is_array($value)) {
        $nested = $this->filterEmptyValuesPreserveKeys($value);
        if (!empty($nested)) {
          $filtered[$key] = $nested;
        }
      }
      elseif ($value !== '' && $value !== NULL) {
        $filtered[$key] = $value;
      }
    }

    return $filtered;
  }

  /**
   * Extracts the positive keywords used in a search query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query from which to extract the keywords.
   *
   * @return string[]
   *   An array of all unique positive keywords used in the query.
   */
  protected function getKeywords(QueryInterface $query) {
    $keys = $query->getOriginalKeys();
    if (!$keys) {
      return [];
    }
    if (is_array($keys)) {
      return $this->flattenKeysArray($keys);
    }

    $keywords_in = preg_split(static::$split, $keys);
    if (!$keywords_in) {
      return [];
    }
    // Assure there are no duplicates. (This is actually faster than
    // array_unique() by a factor of 3 to 4.)
    // Remove quotes from keywords.
    $keywords = [];
    foreach (array_filter($keywords_in) as $keyword) {
      if ($keyword = trim($keyword, "'\"")) {
        $keywords[$keyword] = $keyword;
      }
    }
    return $keywords;
  }

  /**
   * Extracts the positive keywords from a keys array.
   *
   * @param array $keys
   *   A search keys array, as specified by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput().
   *
   * @return string[]
   *   An array of all unique positive keywords contained in the keys array.
   */
  protected function flattenKeysArray(array $keys) {
    if (!empty($keys['#negation'])) {
      return [];
    }

    $keywords = [];
    foreach ($keys as $i => $key) {
      if (!Element::child($i)) {
        continue;
      }
      if (is_array($key)) {
        $keywords += $this->flattenKeysArray($key);
      }
      else {
        $keywords[$key] = $key;
      }
    }

    return $keywords;
  }

  /**
   * Returns snippets from structured data, with certain keywords highlighted.
   *
   * This method properly handles borders between different values/fields to
   * ensure that no snippet goes across multiple values/fields.
   *
   * @param list<array{'field_id': string, 'values': array}> $field_data
   *   Array of field data.
   * @param array $keys
   *   The search keywords entered by the user.
   *
   * @return string|null
   *   A string containing HTML for the excerpt. Or NULL if no excerpt could be
   *   created.
   */
  protected function createExcerptForFields(array $field_data, array $keys): ?string {
    $excerpt_length = (int) $this->configuration['excerpt_length'];
    if (!$field_data || !$excerpt_length) {
      return NULL;
    }

    // Process each field to collect snippets.
    $excerpt_parts = [];
    $found_snippets = [];
    $total_length = 0;
    foreach ($field_data as $field_info) {
      if ($total_length >= $excerpt_length) {
        break;
      }

      $this->processFieldValues(
        $field_info,
        $keys,
        $excerpt_length,
        $this->calculateContextLength($excerpt_length),
        $found_snippets,
        $total_length,
      );
    }

    // Handle fallback if no snippets were found.
    if (!$excerpt_parts && !$found_snippets) {
      if ($this->configuration['excerpt_always']) {
        return $this->createFallbackExcerpt($field_data, $excerpt_length);
      }
      return NULL;
    }

    // Build and return the final excerpt.
    return $this->buildFinalExcerpt($found_snippets, $excerpt_parts, $keys);
  }

  /**
   * Processes field values to extract text snippets based on provided criteria.
   *
   * @param array{'field_id': string, 'values': array} $field_info
   *   An associative array containing information about the field.
   * @param array $keys
   *   An array of keywords to search for within the field values.
   * @param int $excerpt_length
   *   The maximum length of the excerpt to be generated.
   * @param int $context_length
   *   The length of the context to include around each keyword match.
   * @param array &$found_snippets
   *   A reference to an array where extracted snippets will be stored.
   * @param int &$total_length
   *   A reference to the total length counter for the generated excerpts, which
   *   gets updated during processing.
   */
  protected function processFieldValues(
    array $field_info,
    array $keys,
    int $excerpt_length,
    int $context_length,
    array &$found_snippets,
    int &$total_length,
  ): void {
    $field_id = $field_info['field_id'];
    $values = $field_info['values'];

    foreach ($values as $value_index => $value) {
      if ($total_length >= $excerpt_length) {
        break;
      }

      if ($value === NULL) {
        continue;
      }

      $text = $this->prepareTextForExcerpt($value);
      if (!$text) {
        continue;
      }

      $remaining_length = $excerpt_length - $total_length;
      $ranges = $this->findKeywordRanges($text, $keys, $context_length, $remaining_length);

      if (!empty($ranges)) {
        $value_snippets = $this->extractSnippets($text, $ranges, $field_id, $value_index);
        foreach ($value_snippets as $snippet) {
          $found_snippets[] = $snippet;
          $total_length += mb_strlen(strip_tags($snippet['text']));
          if ($total_length >= $excerpt_length) {
            return;
          }
        }
      }
    }
  }

  /**
   * Builds the final excerpt from collected snippets and parts.
   *
   * @param list<array{'text': string, 'field_id': string, 'value_index': int}> $found_snippets
   *   An array of snippets found during content processing.
   * @param array<string, list<string>> $excerpt_parts
   *   An associative array that groups field IDs to their respective snippets.
   * @param list<string> $keys
   *   A list of keywords or phrases to be highlighted within the snippets.
   *
   * @return string|null
   *   The final excerpt, or NULL if no snippets are available.
   */
  protected function buildFinalExcerpt(array $found_snippets, array $excerpt_parts, array $keys): ?string {
    // Apply highlighting to found snippets.
    foreach ($found_snippets as $snippet) {
      $excerpt_parts[$snippet['field_id']][] = $this->highlightField($snippet['text'], $keys, FALSE);
    }

    if (!$excerpt_parts) {
      return NULL;
    }

    // Combine field snippets.
    $ellipses = $this->getEllipses();
    $excerpt = array_map(
      fn ($field_snippets) => implode($ellipses[1], $field_snippets),
      $excerpt_parts,
    );

    return $ellipses[0] . implode($ellipses[1], $excerpt) . $ellipses[2];
  }

  /**
   * Calculates the context length for the given excerpt length.
   *
   * @param int $excerpt_length
   *   The length of the excerpt.
   *
   * @return int
   *   The calculated context length.
   */
  protected function calculateContextLength(int $excerpt_length): int {
    // Get the set excerpt length from the configuration. If the length is too
    // small, only use one fragment.
    $context_length = (int) (round($excerpt_length / 4) - 3);
    if ($context_length < 32) {
      $context_length = (int) (round($excerpt_length / 2) - 1);
    }
    return $context_length;
  }

  /**
   * Prepares text for excerpt creation, cleans HTML and normalizing whitespace.
   *
   * @param string $text
   *   The raw text to prepare.
   *
   * @return string
   *   The cleaned text.
   */
  protected function prepareTextForExcerpt(string $text): string {
    // Remove HTML tags <script> and <style> with all of their contents.
    $text = preg_replace('#<(style|script).*?>.*?</\1>#is', ' ', $text);

    // Prepare text by stripping HTML tags and decoding HTML entities.
    $text = strip_tags(str_replace(['<', '>'], [' <', '> '], $text));
    $text = Html::decodeEntities($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text, ' ');

    return $text;
  }

  /**
   * Finds keyword ranges within a text string.
   *
   * @param string $text
   *   The text to search in.
   * @param list<string> $keys
   *   The search keywords.
   * @param int $context_length
   *   The context length around keywords.
   * @param int $max_length
   *   Maximum length to extract.
   *
   * @return array<int, int>
   *   Array of ranges with start positions as keys and end positions as values.
   */
  protected function findKeywordRanges(string $text, array $keys, int $context_length, int $max_length): array {
    $ranges = [];
    $length = 0;
    $look_start = [];
    $remaining_keys = $keys;
    $text_length = mb_strlen($text);

    while ($length < $max_length && !empty($remaining_keys)) {
      $found_keys = [];
      foreach ($remaining_keys as $key) {
        if ($length >= $max_length) {
          break;
        }

        if (!isset($look_start[$key])) {
          $look_start[$key] = 0;
        }

        $found_position = $this->findKeywordPosition($text, $key, $look_start[$key]);

        if ($found_position !== FALSE) {
          $look_start[$key] = $found_position + 1;
          $found_keys[] = $key;

          // Calculate context boundaries.
          $before = $this->findContextStart($text, $found_position, $context_length);
          $after = $this->findContextEnd($text, $found_position, $context_length, $text_length);

          if ($before !== FALSE && $after !== FALSE && $before < $after) {
            $ranges[$before] = $after;
            $length += $after - $before;
          }
        }
      }
      $remaining_keys = $found_keys;
    }

    return $ranges;
  }

  /**
   * Finds the position of a keyword in text.
   *
   * @param string $text
   *   The text to search in.
   * @param string $key
   *   The keyword to find.
   * @param int $start_offset
   *   The starting position for the search.
   *
   * @return int|false
   *   The position of the keyword or FALSE if not found.
   */
  protected function findKeywordPosition(string $text, string $key, int $start_offset): false|int {
    if (!$this->configuration['highlight_partial']) {
      $regex = '/' . static::$boundary . preg_quote($key, '/') . static::$boundary . '/iu';
      $offset = $start_offset;
      if ($offset > 0) {
        $offset = strlen(mb_substr(' ' . $text, 0, $offset));
      }
      $matches = [];
      if (preg_match($regex, ' ' . $text . ' ', $matches, PREG_OFFSET_CAPTURE, $offset)) {
        $found_position = $matches[0][1];
        return mb_strlen(substr(" $text", 0, $found_position));
      }
      return FALSE;
    }
    return mb_stripos($text, $key, $start_offset, 'UTF-8');
  }

  /**
   * Finds the start position for context around a keyword.
   *
   * @param string $text
   *   The text.
   * @param int $found_position
   *   The position where the keyword was found.
   * @param int $context_length
   *   The desired context length.
   *
   * @return false|int
   *   The start position for context.
   */
  protected function findContextStart(string $text, int $found_position, int $context_length): false|int {
    if ($found_position > $context_length) {
      $before = mb_strpos($text, ' ', $found_position - $context_length);
      if ($before !== FALSE) {
        ++$before;
      }
      if ($before === FALSE || $before > $found_position) {
        return $found_position - $context_length;
      }
      return $before;
    }
    return 0;
  }

  /**
   * Finds the end position for context around a keyword.
   *
   * @param string $text
   *   The text.
   * @param int $found_position
   *   The position where the keyword was found.
   * @param int $context_length
   *   The desired context length.
   * @param int $text_length
   *   The total text length.
   *
   * @return false|int
   *   The end position for context.
   */
  protected function findContextEnd(string $text, int $found_position, int $context_length, int $text_length): false|int {
    if ($text_length > $found_position + $context_length) {
      return mb_strrpos(mb_substr($text, 0, $found_position + $context_length), ' ', $found_position);
    }
    return $text_length;
  }

  /**
   * Extracts snippets from text based on found ranges.
   *
   * @param string $text
   *   The text to extract from.
   * @param array $ranges
   *   The ranges to extract.
   * @param string $field_id
   *   The field ID this text belongs to.
   * @param int $value_index
   *   The value index within the field.
   *
   * @return array
   *   Array of snippet data.
   */
  protected function extractSnippets(string $text, array $ranges, string $field_id, int $value_index): array {
    // Sort ranges and collapse overlapping ones.
    ksort($ranges);
    $new_ranges = [];
    $working_from = $working_to = NULL;

    foreach ($ranges as $this_from => $this_to) {
      if ($working_from === NULL) {
        $working_from = $this_from;
        $working_to = $this_to;
        continue;
      }
      if ($this_from <= $working_to) {
        $working_to = max($working_to, $this_to);
      }
      else {
        $new_ranges[$working_from] = $working_to;
        $working_from = $this_from;
        $working_to = $this_to;
      }
    }
    $new_ranges[$working_from] = $working_to;

    // Extract text snippets.
    $snippets = [];
    foreach ($new_ranges as $from => $to) {
      $snippet_text = Html::escape(mb_substr($text, $from, $to - $from));
      $snippets[] = [
        'text' => $snippet_text,
        'field_id' => $field_id,
        'value_index' => $value_index,
      ];
    }

    return $snippets;
  }

  /**
   * Fallback excerpt when no keywords are found but excerpt_always is enabled.
   *
   * @param array $field_data
   *   The field data array.
   * @param int $excerpt_length
   *   The desired excerpt length.
   *
   * @return string|null
   *   The fallback excerpt.
   */
  protected function createFallbackExcerpt(array $field_data, int $excerpt_length): ?string {
    $ellipses = $this->getEllipses();

    // Take text from the first available field/value.
    foreach ($field_data as $field_info) {
      foreach ($field_info['values'] as $value) {
        if ($value === NULL) {
          continue;
        }

        $text = $this->prepareTextForExcerpt($value);
        if ($text) {
          $snippet = mb_substr($text, 0, $excerpt_length);
          $pos = mb_strrpos($snippet, ' ');
          if ($pos > $excerpt_length / 2) {
            $snippet = mb_substr($snippet, 0, $pos);
          }
          return trim($snippet) . $ellipses[2];
        }
      }
    }

    return NULL;
  }

  /**
   * Returns snippets from a piece of text, with certain keywords highlighted.
   *
   * Should not be called anymore, the stub is present only for backwards
   * compatibility.
   *
   * @param string $text
   *   The text to extract fragments from.
   * @param array $keys
   *   The search keywords entered by the user.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown always.
   */
  protected function createExcerpt($text, array $keys) {
    // @todo Remove for version 2.0.0.
    throw new SearchApiException('This method has been removed.');
  }

  /**
   * Marks occurrences of the search keywords in a text field.
   *
   * @param string $text
   *   The text of the field.
   * @param array $keys
   *   The search keywords entered by the user.
   * @param bool $html
   *   (optional) Whether the text can contain HTML tags or not. In the former
   *   case, text inside tags (that is, tag names and attributes) won't be
   *   highlighted.
   *
   * @return string
   *   The given text with all occurrences of search keywords highlighted.
   */
  protected function highlightField($text, array $keys, $html = TRUE) {
    $text = "$text";
    if ($html) {
      $regex = <<<'REGEX'
%
  (                # Capturing group around the whole expression, so
                   # PREG_SPLIT_DELIM_CAPTURE works correctly
    \s*+           # Optional leading whitespace (possessive since backtracking
                   # would make no sense here)
    (?:            # One or more HTML tags
      <            # Start of HTML tag
      /?           # Could be a closing tag
      [[:alpha:]]  # Tag names always start with a letter
      [^>]*        # Anything except the angle bracket closing the tag
      >            # End of HTML tag
    )+             # End: One or more HTML tags
    \s*            # Optional trailing whitespace
  )                # End: Capturing group
%ix
REGEX;

      $texts = preg_split($regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
      if ($texts === FALSE) {
        $args = [
          '%error_num' => preg_last_error(),
        ];
        $this->getLogger()->warning('A PCRE error (#%error_num) occurred during results highlighting.', $args);
        return $text;
      }
      $textsCount = count($texts);
      for ($i = 0; $i < $textsCount; $i += 2) {
        $texts[$i] = $this->highlightField($texts[$i], $keys, FALSE);
      }
      return implode('', $texts);
    }
    $keys = implode('|', array_map('preg_quote', $keys, array_fill(0, count($keys), '/')));
    // If "Highlight partial matches" is disabled, we only want to highlight
    // matches that are complete words. Otherwise, we want all of them.
    $boundary = !$this->configuration['highlight_partial'] ? static::$boundary : '';
    $regex = '/' . $boundary . '(?:' . $keys . ')' . $boundary . '/iu';
    $replace = $this->configuration['prefix'] . '\0' . $this->configuration['suffix'];
    $text = preg_replace($regex, $replace, ' ' . $text . ' ');
    return trim($text);
  }

  /**
   * Retrieves the translated separators for excerpts.
   *
   * Defaults to Unicode ellipses (…) on all positions.
   *
   * @return string[]
   *   A numeric array containing three elements: the separator to put at the
   *   front of the excerpt (if that is not the front of the string), the
   *   separator to put in between different portions of the text, and the
   *   separator to append at the end of the excerpt if it doesn't end with the
   *   end of the text.
   */
  protected function getEllipses() {
    // Combine the text chunks with "…" separators. The "…" needs to be
    // translated. Let translators have the … separator text as one chunk.
    $ellipses = explode('@excerpt', $this->t('… @excerpt … @excerpt …'));
    return $ellipses;
  }

}
