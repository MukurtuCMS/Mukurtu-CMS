<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Lists a vocabulary's terms as options for a select list.
 *
 * A select can't reliably indent its options, and the dashes core uses to show
 * depth are read aloud, so each nested term shows its full path, such as
 * "Washington › Spokane › Hillyard". Names are in the current content language.
 *
 * A caller that displays the options in a render-cached context must add the
 * 'languages:language_content' cache context and the vocabulary's list cache
 * tag to its own cacheable metadata.
 */
class TermPathOptions {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTranslationResolver $entityTranslationResolver,
  ) {}

  /**
   * Gets a vocabulary's terms in tree order.
   *
   * Each term is followed by the terms nested under it. Terms with the same
   * parent are ordered by weight, then by translated name.
   *
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return string[]
   *   Term paths keyed by term ID.
   */
  public function getOptions(string $vid): array {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
    $children = [];
    foreach ($terms as $tid => $term) {
      $parent = (int) $term->get('parent')->target_id;
      $children[isset($terms[$parent]) ? $parent : 0][(int) $tid] = [
        'name' => (string) $this->entityTranslationResolver->translate($term)->label(),
        'weight' => (int) $term->getWeight(),
      ];
    }

    $options = [];
    $this->addBranch(0, '', $children, $options);
    return $options;
  }

  /**
   * Adds a term's children, and everything nested under them, to the options.
   */
  protected function addBranch(int $parent, string $path, array $children, array &$options): void {
    $branch = $children[$parent] ?? [];
    uasort($branch, fn ($a, $b) => [$a['weight'], mb_strtolower($a['name'])] <=> [$b['weight'], mb_strtolower($b['name'])]);
    foreach ($branch as $tid => $term) {
      $options[$tid] = $path . $term['name'];
      $this->addBranch($tid, $options[$tid] . ' › ', $children, $options);
    }
  }

}
