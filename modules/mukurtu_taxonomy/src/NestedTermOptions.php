<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy;

use Drupal\Component\Utility\Html;

/**
 * Shows nested terms in checkbox and radio fields as groups (#2523).
 *
 * Core lists the terms of a vocabulary that allows nesting with a dash per
 * level in each label, such as "-Weaving", which screen readers read out and
 * which says nothing about which term is the parent. This removes the dashes
 * and puts each term's nested terms right after it, indented, in a group
 * named after it, so screen readers announce the grouping too. The options and
 * their values don't change.
 *
 * @see mukurtu_taxonomy_options_list_alter()
 * @see mukurtu_taxonomy_field_widget_single_element_options_buttons_form_alter()
 */
class NestedTermOptions {

  /**
   * Gets the parent and depth of each term in some vocabularies.
   *
   * @param string[] $vids
   *   Vocabulary IDs.
   *
   * @return array
   *   ['parent' => int, 'depth' => int] keyed by term ID.
   */
  public static function getTree(array $vids): array {
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $tree = [];
    foreach ($vids as $vid) {
      foreach ($storage->loadTree($vid) as $term) {
        $tree[(int) $term->tid] = ['parent' => (int) ($term->parents[0] ?? 0), 'depth' => (int) $term->depth];
      }
    }
    return $tree;
  }

  /**
   * Removes core's depth dashes from term option labels.
   *
   * Only as many dashes as the term's depth are removed, so a term whose own
   * name starts with a dash keeps it.
   *
   * @param array $options
   *   Labels keyed by term ID.
   * @param array $tree
   *   The vocabulary tree, from getTree().
   *
   * @return array
   *   The labels without depth dashes.
   */
  public static function removeDashes(array $options, array $tree): array {
    foreach ($options as $tid => $label) {
      $depth = $tree[(int) $tid]['depth'] ?? 0;
      $label = (string) $label;
      if ($depth && str_starts_with($label, str_repeat('-', $depth))) {
        $options[$tid] = substr($label, $depth);
      }
    }
    return $options;
  }

  /**
   * Form #process callback: groups nested terms after checkboxes or radios.
   *
   * Runs after core has made a checkbox or radio for each option. Before a
   * term's first nested term it opens a group named after the term, and it
   * closes the group after the term's last nested term. Groups are added as
   * markup around the existing elements, so their names and values don't
   * change. Options whose parent isn't offered are grouped under their
   * nearest offered ancestor.
   */
  public static function processElement(array $element): array {
    $tree = $element['#mukurtu_term_tree'] ?? [];
    $options = $element['#options'] ?? [];
    if (!array_filter($tree, fn ($term) => $term['parent'] !== 0)) {
      return $element;
    }

    // Themes style options by their direct parent, for example Gin shows a
    // checkbox as a toggle switch unless its parent is .form-checkboxes, so
    // each group carries the same class as the list it's in.
    $list_class = ($element['#type'] ?? '') === 'radios' ? 'form-radios' : 'form-checkboxes';
    $open = [];
    $previous = NULL;
    foreach (array_keys($options) as $tid) {
      if (!isset($element[$tid])) {
        continue;
      }
      $ancestors = static::offeredAncestors((int) $tid, $tree, $options);

      // Close the groups this option isn't in.
      while ($open && !in_array(end($open), $ancestors, TRUE)) {
        array_pop($open);
        $element[$previous]['#suffix'] = ($element[$previous]['#suffix'] ?? '') . '</div>';
      }
      // Open the groups it's in that aren't open yet.
      foreach (array_diff($ancestors, $open) as $ancestor) {
        $name = Html::escape(strip_tags((string) $options[$ancestor]));
        $element[$tid]['#prefix'] = ($element[$tid]['#prefix'] ?? '') . '<div class="' . $list_class . ' mukurtu-nested-term-options__group" role="group" aria-label="' . $name . '">';
        $open[] = $ancestor;
      }
      $previous = $tid;
    }
    if ($previous !== NULL && $open) {
      $element[$previous]['#suffix'] = ($element[$previous]['#suffix'] ?? '') . str_repeat('</div>', count($open));
    }

    $element['#attached']['library'][] = 'mukurtu_taxonomy/nested-term-options';
    return $element;
  }

  /**
   * Gets a term's ancestors that are also options, top-level first.
   *
   * @return int[]
   *   Term IDs.
   */
  protected static function offeredAncestors(int $tid, array $tree, array $options): array {
    $ancestors = [];
    $parent = $tree[$tid]['parent'] ?? 0;
    while ($parent && !in_array($parent, $ancestors, TRUE)) {
      if (isset($options[$parent])) {
        $ancestors[] = $parent;
      }
      $parent = $tree[$parent]['parent'] ?? 0;
    }
    return array_reverse($ancestors);
  }

}
