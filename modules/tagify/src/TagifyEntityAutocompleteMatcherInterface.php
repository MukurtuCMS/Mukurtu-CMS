<?php

namespace Drupal\tagify;

/**
 * Matcher class to get autocompletion results for entity reference.
 */
interface TagifyEntityAutocompleteMatcherInterface {

  /**
   * Gets matched labels based on a given search string.
   *
   * @param string $target_type
   *   The ID of the target entity type.
   * @param string $selection_handler
   *   The plugin ID of the entity reference selection handler.
   * @param array $selection_settings
   *   An array of settings that will be passed to the selection handler.
   * @param string $string
   *   (optional) The label of the entity to query by.
   * @param array $selected
   *   (optional) An array of already selected items.
   *
   * @return array
   *   An array of matched entity labels, in the format required by the AJAX
   *   autocomplete API (e.g. array('value' => $value, 'label' => $label)).
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the current user doesn't have access to the specified entity.
   *
   * @see \Drupal\system\Controller\TagifyEntityAutocompleteController
   */
  public function getMatches($target_type, $selection_handler, array $selection_settings, $string = '', array $selected = []);

}
