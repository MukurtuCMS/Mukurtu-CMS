<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_core\Service\EntityTranslationResolver;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows a public page listing a vocabulary's terms (#2612).
 *
 * Terms show as cards in columns, which suit groups of very different heights.
 * A mostly nested vocabulary gets a card per top-level term, and a long flat one
 * gets a card per letter. A short, mostly flat vocabulary stays a plain list.
 * From TOOLS_MINIMUM terms the page adds a filter box and letter links.
 */
class VocabularyPageController extends ControllerBase {

  /**
   * The number of terms from which the filter and letter links show.
   */
  const TOOLS_MINIMUM = 30;

  public function __construct(
    protected EntityTranslationResolver $entityTranslationResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_core.entity_translation_resolver'));
  }

  /**
   * Gets the page title.
   */
  public function title(VocabularyInterface $taxonomy_vocabulary): string {
    return (string) $taxonomy_vocabulary->label();
  }

  /**
   * Builds the page.
   */
  public function build(VocabularyInterface $taxonomy_vocabulary): array {
    // loadTree() doesn't check access, so unpublished terms are removed here.
    // Terms nested under one go with it, since their parent isn't listed.
    $terms = array_filter(
      $this->entityTypeManager()->getStorage('taxonomy_term')->loadTree($taxonomy_vocabulary->id(), 0, NULL, TRUE),
      fn ($term) => $term->access('view'),
    );
    $by_parent = [];
    foreach ($terms as $term) {
      $by_parent[(int) reset($term->parents)][] = $term;
    }
    $items = $this->buildItems($by_parent);
    // Terms under an unpublished parent aren't listed, so aren't counted.
    $total = $this->countItems($items);
    $tools = $total >= static::TOOLS_MINIMUM;

    // Nesting is the useful grouping only when most terms are nested. With a
    // few nested terms, a card per top-level term would be mostly empty cards.
    $groups = [];
    if ($total - count($items) >= count($items) && $items) {
      $groups = $this->groupByParent($items);
    }
    elseif ($tools) {
      $groups = $this->groupByLetter($items);
    }

    return [
      '#theme' => 'mukurtu_vocabulary_page',
      '#description' => $taxonomy_vocabulary->getDescription(),
      '#items' => $groups ? [] : $items,
      '#groups' => $groups,
      '#letters' => $tools ? $this->getLetterLinks($groups) : [],
      '#tools' => $tools,
      '#attached' => ['library' => ['mukurtu_taxonomy/vocabulary-page']],
      '#cache' => [
        'contexts' => ['languages:language_content', 'languages:language_interface', 'user.permissions'],
        'tags' => array_merge($taxonomy_vocabulary->getCacheTags(), ['taxonomy_term_list:' . $taxonomy_vocabulary->id()]),
      ],
    ];
  }

  /**
   * Builds the nested list items.
   *
   * @param array $by_parent
   *   A vocabulary's terms, keyed by the ID of their parent.
   * @param int $parent
   *   The ID of the term whose children to build.
   *
   * @return array
   *   Items with a name, url and below, ordered by weight, then by translated
   *   name, because loadTree() orders names in the default language.
   */
  protected function buildItems(array $by_parent, int $parent = 0): array {
    $items = [];
    foreach ($by_parent[$parent] ?? [] as $term) {
      $items[] = [
        'name' => (string) $this->entityTranslationResolver->translate($term)->label(),
        'url' => $term->toUrl()->toString(),
        'weight' => (int) $term->getWeight(),
        'below' => $this->buildItems($by_parent, (int) $term->id()),
      ];
    }
    usort($items, fn ($a, $b) => [$a['weight'], mb_strtolower($a['name'])] <=> [$b['weight'], mb_strtolower($b['name'])]);
    return $items;
  }

  /**
   * Counts items and everything nested under them.
   */
  protected function countItems(array $items): int {
    return array_reduce($items, fn ($count, $item) => $count + 1 + $this->countItems($item['below']), 0);
  }

  /**
   * Gets the first letter of a name.
   */
  protected function firstLetter(string $name): string {
    return mb_strtoupper(mb_substr($name, 0, 1));
  }

  /**
   * Makes a card for each top-level item, holding the terms nested under it.
   *
   * @return array
   *   Cards with a heading, url, letter, id and items.
   */
  protected function groupByParent(array $items): array {
    $groups = [];
    foreach ($items as $item) {
      $groups[] = [
        'heading' => $item['name'],
        'url' => $item['url'],
        'letter' => $this->firstLetter($item['name']),
        // Numbered, since a letter or a name may not be valid in an ID.
        'id' => 'vocabulary-group-' . (count($groups) + 1),
        'items' => $item['below'],
      ];
    }
    return $groups;
  }

  /**
   * Makes a card for each first letter of the top-level items.
   *
   * @return array
   *   Cards with a heading, url, letter, id and items.
   */
  protected function groupByLetter(array $items): array {
    $letters = [];
    foreach ($items as $item) {
      $letters[$this->firstLetter($item['name'])][] = $item;
    }
    ksort($letters, SORT_STRING);

    $groups = [];
    foreach ($letters as $letter => $letter_items) {
      $groups[] = [
        'heading' => (string) $letter,
        'url' => NULL,
        'letter' => (string) $letter,
        'id' => 'vocabulary-group-' . (count($groups) + 1),
        'items' => $letter_items,
      ];
    }
    return $groups;
  }

  /**
   * Gets a link to the first card for each letter in use.
   *
   * Only letters in use are listed, because many Mukurtu vocabularies hold
   * names in languages with their own alphabets.
   *
   * @return array
   *   Links with a letter and an id, ordered by letter.
   */
  protected function getLetterLinks(array $groups): array {
    $letters = [];
    foreach ($groups as $group) {
      $letters[$group['letter']] ??= ['letter' => $group['letter'], 'id' => $group['id']];
    }
    ksort($letters, SORT_STRING);
    return array_values($letters);
  }

}
