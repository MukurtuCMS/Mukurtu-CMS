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
 * Nested terms show under their parents. From TOOLS_MINIMUM terms the page adds
 * a filter box and letter links, and short vocabularies stay a plain list.
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
    $items = $this->buildItems($terms);
    $tools = count($terms) >= static::TOOLS_MINIMUM;

    return [
      '#theme' => 'mukurtu_vocabulary_page',
      '#description' => $taxonomy_vocabulary->getDescription(),
      '#items' => $tools ? [] : $items,
      '#groups' => $tools ? $this->groupByLetter($items) : [],
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
   * @param \Drupal\taxonomy\TermInterface[] $terms
   *   A vocabulary's terms, from loadTree().
   * @param int $parent
   *   The ID of the term whose children to build.
   *
   * @return array
   *   Items with a name, url and below, ordered by weight, then by translated
   *   name, because loadTree() orders names in the default language.
   */
  protected function buildItems(array $terms, int $parent = 0): array {
    $items = [];
    foreach ($terms as $term) {
      if ((int) reset($term->parents) !== $parent) {
        continue;
      }
      $items[] = [
        'name' => (string) $this->entityTranslationResolver->translate($term)->label(),
        'url' => $term->toUrl()->toString(),
        'weight' => (int) $term->getWeight(),
        'below' => $this->buildItems($terms, (int) $term->id()),
      ];
    }
    usort($items, fn ($a, $b) => [$a['weight'], mb_strtolower($a['name'])] <=> [$b['weight'], mb_strtolower($b['name'])]);
    return $items;
  }

  /**
   * Groups top-level items by the first letter of their name.
   *
   * Only letters in use are listed, because many Mukurtu vocabularies hold
   * names in languages with their own alphabets.
   *
   * @return array
   *   Groups with a letter, id and items.
   */
  protected function groupByLetter(array $items): array {
    $letters = [];
    foreach ($items as $item) {
      $letters[mb_strtoupper(mb_substr($item['name'], 0, 1))][] = $item;
    }
    ksort($letters, SORT_STRING);

    $groups = [];
    foreach ($letters as $letter => $letter_items) {
      $groups[] = [
        'letter' => (string) $letter,
        // Numbered, since a letter may not be valid in an ID.
        'id' => 'vocabulary-letter-' . (count($groups) + 1),
        'items' => $letter_items,
      ];
    }
    return $groups;
  }

}
