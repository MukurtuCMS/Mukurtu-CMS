<?php

declare(strict_types=1);

namespace Drupal\mukurtu_dictionary\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
use Drupal\mukurtu_dictionary\Form\MukurtuDictionaryLanguagesSettingsForm;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Marks dictionary text that is in the word's language with a lang attribute.
 *
 * Without it, a screen reader reads Indigenous-language words with the page
 * language's pronunciation rules (WCAG 2.1 SC 3.1.2, #2328). The code comes
 * from the Language code field on the word's language term; when that is
 * blank, nothing is added.
 *
 * Only fields that hold text in the word's language are marked. Definition,
 * pronunciation, translation and word origin are usually written in the
 * site's language, so they keep the page language unless a site says
 * otherwise on the dictionary languages settings form (#2353): some
 * communities write a monolingual definition, or use in-language spelling
 * rather than a phonetic guide for pronunciation.
 */
final class LanguageAttributeHooks {

  /**
   * Fields in the word's language, keyed by entity type and bundle.
   */
  private const FIELDS = [
    'node' => [
      'dictionary_word' => ['title', 'field_alternate_spelling'],
    ],
    'paragraph' => [
      'dictionary_word_entry' => ['field_word_entry_term', 'field_alternate_spelling'],
      'sample_sentence' => ['field_sentence'],
    ],
  ];

  /**
   * Fields a site may additionally mark, by entity type and bundle.
   *
   * These hold the site's language on most sites, so none is marked unless
   * the site selects it. The list is fixed here rather than derived from the
   * bundle's fields: marking the wrong field is worse than marking none, so
   * the choice is limited to the fields where being in the word's language
   * is plausible.
   */
  private const OPTIONAL_FIELDS = [
    'node' => [
      'dictionary_word' => [
        'field_definition',
        'field_pronunciation',
        'field_translation',
        'field_word_origin',
      ],
    ],
    'paragraph' => [
      'dictionary_word_entry' => [
        'field_definition',
        'field_pronunciation',
        'field_translation',
        'field_word_origin',
      ],
    ],
  ];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * The fields to mark for an entity, including anything the site added.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being rendered.
   *
   * @return string[]
   *   Field names to mark.
   */
  private function fieldsFor(EntityInterface $entity): array {
    $type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $fields = self::FIELDS[$type][$bundle] ?? [];

    $optional = self::OPTIONAL_FIELDS[$type][$bundle] ?? [];
    if ($optional) {
      $chosen = $this->configFactory->get(MukurtuDictionaryLanguagesSettingsForm::SETTINGS)->get('in_language_fields') ?? [];
      // Intersect rather than trust the stored list, so a field that is
      // renamed or removed later cannot resurrect through stale config.
      $fields = array_merge($fields, array_values(array_intersect($optional, $chosen)));
    }

    return $fields;
  }

  /**
   * Implements hook_preprocess_field().
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    $entity = $variables['element']['#object'] ?? NULL;
    if (!$entity instanceof EntityInterface) {
      return;
    }
    if (!in_array($variables['field_name'], $this->fieldsFor($entity), TRUE)) {
      return;
    }

    $code = self::findWord($entity)?->getLanguageCode();
    if ($code === NULL) {
      return;
    }

    foreach ($variables['items'] as $delta => $item) {
      $variables['items'][$delta]['attributes']->setAttribute('lang', $code);
    }

    // A single value with a hidden label is printed on the wrapper element
    // instead of an item wrapper, the same case where core merges item
    // attributes into the wrapper. A visible label is in the page language,
    // so the wrapper is left alone otherwise.
    if ($variables['label_hidden'] && !$variables['multiple']) {
      $variables['attributes']['lang'] = $code;
    }
  }

  /**
   * Implements hook_preprocess_page_title().
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(array &$variables): void {
    $node = $this->routeMatch->getParameter('node');
    if ($this->routeMatch->getRouteName() !== 'entity.node.canonical' || !$node instanceof DictionaryWordInterface) {
      return;
    }
    $code = $node->getLanguageCode();
    if ($code !== NULL) {
      $variables['title_attributes']['lang'] = $code;
    }
  }

  /**
   * Implements hook_node_view().
   *
   * The code lives on the language term and the field list lives in config,
   * so a rendered word has to be invalidated when either changes. Paragraphs
   * are not render cached, so the node is the only cache entry that needs it.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof DictionaryWordInterface) {
      return;
    }
    $metadata = BubbleableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($this->configFactory->get(MukurtuDictionaryLanguagesSettingsForm::SETTINGS));
    if ($term = $entity->getLanguageTerm()) {
      $metadata->addCacheableDependency($term);
    }
    $metadata->applyTo($build);
  }

  /**
   * Finds the dictionary word an entity belongs to.
   *
   * Sample sentences can sit on the word itself or on one of its additional
   * word entries, so this walks up through paragraph parents.
   */
  public static function findWord(EntityInterface $entity): ?DictionaryWordInterface {
    // A word nests paragraphs at most two deep; the limit guards against a
    // corrupt parent chain.
    for ($depth = 0; $depth < 5 && $entity; $depth++) {
      if ($entity instanceof DictionaryWordInterface) {
        return $entity;
      }
      if (!$entity instanceof ParagraphInterface) {
        return NULL;
      }
      $entity = $entity->getParentEntity();
    }
    return NULL;
  }

}
