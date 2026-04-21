<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Makes searches insensitive to accents and other non-ASCII characters.
 */
#[SearchApiProcessor(
  id: 'transliteration',
  label: new TranslatableMarkup('Transliteration'),
  description: new TranslatableMarkup('Makes searches insensitive to accents and other non-ASCII characters.'),
  stages: [
    'pre_index_save' => 0,
    'preprocess_index' => -20,
    'preprocess_query' => -20,
  ],
)]
class Transliteration extends FieldsProcessorPluginBase {

  /**
   * The transliteration service to use.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliterator;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|null
   */
  protected $languageManager;

  /**
   * The language to use for transliterating.
   *
   * @var string
   */
  protected $langcode;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setTransliterator($container->get('transliteration'));
    $processor->setLanguageManager($container->get('language_manager'));

    return $processor;
  }

  /**
   * Retrieves the transliterator.
   *
   * @return \Drupal\Component\Transliteration\TransliterationInterface
   *   The transliterator.
   */
  public function getTransliterator() {
    return $this->transliterator ?: \Drupal::service('transliteration');
  }

  /**
   * Sets the transliterator.
   *
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliterator
   *   The new transliterator.
   *
   * @return $this
   */
  public function setTransliterator(TransliterationInterface $transliterator) {
    $this->transliterator = $transliterator;
    return $this;
  }

  /**
   * Retrieves the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  public function getLanguageManager() {
    return $this->languageManager ?: \Drupal::languageManager();
  }

  /**
   * Sets the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The new language manager.
   *
   * @return $this
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
    return $this;
  }

  /**
   * Retrieves the language code.
   *
   * @return string
   *   the language code.
   */
  public function getLangcode() {
    return $this->langcode ?: $this->getLanguageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
  }

  /**
   * Sets the language code.
   *
   * @param string|null $langcode
   *   The new language code, if any.
   *
   * @return $this
   */
  public function setLangcode($langcode) {
    $this->langcode = $langcode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query): void {
    $languages = $query->getLanguages();
    if ($languages && count($languages) === 1) {
      $this->setLangcode(reset($languages));
    }
    parent::preprocessSearchQuery($query);
    $this->setLangcode(NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    foreach ($items as $item) {
      $this->setLangcode($item->getLanguage());
      foreach ($item->getFields() as $name => $field) {
        if ($this->testField($name, $field)) {
          $this->processField($field);
        }
      }
    }
    $this->setLangcode(NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $value = $this->getTransliterator()->transliterate($value, $this->getLangcode());
  }

}
