<?php

declare(strict_types=1);

namespace Drupal\mukurtu_dictionary\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
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
 * translation and pronunciation are usually written in the site's language,
 * so they keep the page language.
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

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_preprocess_field().
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    $entity = $variables['element']['#object'] ?? NULL;
    if (!$entity instanceof EntityInterface) {
      return;
    }
    $fields = self::FIELDS[$entity->getEntityTypeId()][$entity->bundle()] ?? [];
    if (!in_array($variables['field_name'], $fields, TRUE)) {
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
   * The code lives on the language term, so a rendered word has to be
   * invalidated when the term changes. Paragraphs are not render cached, so
   * the node is the only cache entry that needs it.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity instanceof DictionaryWordInterface && $term = $entity->getLanguageTerm()) {
      BubbleableMetadata::createFromRenderArray($build)
        ->addCacheableDependency($term)
        ->applyTo($build);
    }
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
