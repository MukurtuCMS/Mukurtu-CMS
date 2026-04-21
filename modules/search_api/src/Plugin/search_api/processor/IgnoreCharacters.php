<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Configure types of characters which should be ignored for searches.
 */
#[SearchApiProcessor(
  id: 'ignore_character',
  label: new TranslatableMarkup('Ignore characters'),
  description: new TranslatableMarkup('Configure types of characters which should be ignored for searches.'),
  stages: [
    'pre_index_save' => 0,
    'preprocess_index' => -10,
    'preprocess_query' => -10,
  ],
)]
class IgnoreCharacters extends FieldsProcessorPluginBase {

  /**
   * The escaped regular expression for ignorable characters.
   *
   * @var string
   */
  protected $ignorable;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();

    $configuration += [
      'ignorable' => "['¿¡!?,.:;]",
      'ignorable_classes' => [
        'Pc',
        'Pd',
        'Pe',
        'Pf',
        'Pi',
        'Po',
        'Ps',
      ],
    ];

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['ignorable'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Strip by regular expression'),
      '#description' => $this->t('Specify characters which should be removed from fulltext fields and search strings, as a <a href=":url">PCRE regular expression</a>.', [':url' => Url::fromUri('https://secure.php.net/manual/reference.pcre.pattern.syntax.php')->toString()]),
      '#default_value' => $this->configuration['ignorable'],
      '#maxlength' => 1000,
    ];

    $character_sets = $this->getCharacterSets();
    $form['strip'] = [
      '#type' => 'details',
      '#title' => $this->t('Strip by character property'),
      '#description' => $this->t('Specify <a href=":url">Unicode character properties</a> of characters to be ignored.', [':url' => Url::fromUri('https://en.wikipedia.org/wiki/Unicode_character_property')->toString()]),
      '#open' => FALSE,
      '#maxlength' => 300,

    ];
    $classes = $this->configuration['ignorable_classes'];
    $form['strip']['character_sets'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Ignored character properties'),
      '#options' => $character_sets,
      '#default_value' => array_combine($classes, $classes),
      '#multiple' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $ignorable = str_replace('/', '\/', $form_state->getValue('ignorable', ''));
    if ($ignorable !== '' && @preg_match('/(' . $ignorable . ')+/u', '') === FALSE) {
      $el = $form['ignorable'];
      $form_state->setError($el, $el['#title'] . ': ' . $this->t('The entered text is no valid regular expression.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $form_state->getValues();
    unset($config['strip']);
    // Get our own version of 'ignorable_classes' from form values.
    $classes = $form_state->getValue(['strip', 'character_sets'], []);
    $config['ignorable_classes'] = array_values(array_filter($classes));
    $this->setConfiguration($config);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    if ($this->configuration['ignorable']) {
      if (!isset($this->ignorable)) {
        $this->ignorable = str_replace('/', '\/', $this->configuration['ignorable']);
      }
      $value = preg_replace('/' . $this->ignorable . '+/u', '', $value);
    }

    // Loop over the character sets and strip the characters from the text.
    foreach ($this->configuration['ignorable_classes'] ?? [] as $character_set) {
      $regex = $this->getFormatRegularExpression($character_set);
      if ($regex) {
        $value = preg_replace('/[' . $regex . ']+/u', '', $value);
      }
    }
  }

  /**
   * Retrieves an options list for available Unicode character properties.
   *
   * @return string[]
   *   An options list with all available Unicode character properties.
   */
  protected function getCharacterSets() {
    return [
      'Pc' => $this->t('Punctuation, Connector Characters'),
      'Pd' => $this->t('Punctuation, Dash Characters'),
      'Pe' => $this->t('Punctuation, Close Characters'),
      'Pf' => $this->t('Punctuation, Final quote Characters'),
      'Pi' => $this->t('Punctuation, Initial quote Characters'),
      'Po' => $this->t('Punctuation, Other Characters'),
      'Ps' => $this->t('Punctuation, Open Characters'),

      'Cc' => $this->t('Other, Control Characters'),
      'Cf' => $this->t('Other, Format Characters'),
      'Co' => $this->t('Other, Private Use Characters'),

      'Mc' => $this->t('Mark, Spacing Combining Characters'),
      'Me' => $this->t('Mark, Enclosing Characters'),
      'Mn' => $this->t('Mark, Nonspacing Characters'),

      'Sc' => $this->t('Symbol, Currency Characters'),
      'Sk' => $this->t('Symbol, Modifier Characters'),
      'Sm' => $this->t('Symbol, Math Characters'),
      'So' => $this->t('Symbol, Other Characters'),

      'Zl' => $this->t('Separator, Line Characters'),
      'Zp' => $this->t('Separator, Paragraph Characters'),
      'Zs' => $this->t('Separator, Space Characters'),
    ];
  }

  /**
   * Retrieves a regular expression for a certain Unicode character property.
   *
   * @param string $property
   *   The abbreviation of the character property for which to get the regular
   *   expression.
   *
   * @return string|null
   *   The regular expression for the property, or NULL if it could not be
   *   found.
   */
  protected function getFormatRegularExpression($property) {
    $class = 'Drupal\search_api\Plugin\search_api\processor\Resources\\' . $property;
    if (class_exists($class) && in_array('Drupal\search_api\Plugin\search_api\processor\Resources\UnicodeCharacterPropertyInterface', class_implements($class))) {
      return $class::getRegularExpression();
    }
    return NULL;
  }

}
