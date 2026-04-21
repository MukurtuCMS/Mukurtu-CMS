<?php

namespace Drupal\tagify_user_list;

use Drupal\Component\Utility\Html;
use Drupal\tagify\TagifyEntityAutocompleteMatcher;

/**
 * Matcher class to get autocompletion results for entity reference type user.
 */
class TagifyUserListEntityAutocompleteMatcher extends TagifyEntityAutocompleteMatcher {

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
   *   An array of selected values.
   *
   * @return array
   *   An array of matched entity labels, in the format required by the AJAX
   *   autocomplete API (e.g. array('value' => $value, 'label' => $label)).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see \Drupal\system\Controller\EntityAutocompleteController
   */
  public function getMatches($target_type, $selection_handler, array $selection_settings, $string = '', array $selected = []): array {
    $matches = [];
    $storage = $this->entityTypeManager->getStorage($target_type);
    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);
    if ($handler !== FALSE) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, $match_limit + count($selected));
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          // Filter out already selected items.
          if (in_array($entity_id, $selected, TRUE)) {
            continue;
          }
          $info_label = NULL;
          $entity = $storage->load($entity_id);
          if (!empty($selection_settings['info_label'])) {
            $info_label = $this->token->replacePlain($selection_settings['info_label'], [$target_type => $entity], ['clear' => TRUE]);
            $info_label = trim(preg_replace('/\s+/', ' ', $info_label));
          }
          $context = ['entity' => $entity] + $options;
          $this->moduleHandler->alter('tagify_autocomplete_match', $label, $info_label, $context);

          if ($info_label !== NULL) {
            $info_label = self::filterHtmlWithImages($info_label);
          }

          if ($label !== NULL) {
            $matches[$entity_id] = $this->buildTagifyUserListItem($target_type, $entity_id, $label, $info_label, $selection_settings['image'], $selection_settings['image_style']);
          }
        }
      }
      if ($match_limit > 0) {
        $matches = array_slice($matches, 0, $match_limit, TRUE);
      }
      $this->moduleHandler->alterDeprecated('Use hook_tagify_autocomplete_match_alter() instead.', 'tagify_user_list_autocomplete_matches', $matches, $options);
    }

    return array_values($matches);
  }

  /**
   * Builds the array that represents the entity in tagify autocomplete user.
   *
   * @param string $target_type
   *   The target entity type ID.
   * @param string $entity_id
   *   The matched entity id.
   * @param string $label
   *   The matched label.
   * @param string|null $info_label
   *   The matched info label.
   * @param string $image
   *   The image field to be used.
   * @param string $image_style
   *   The image style to be used.
   *
   * @return array
   *   The tagify item array. Associative array with the following keys:
   *   - 'entity_id':
   *     The referenced entity ID.
   *   - 'label':
   *     The text to be shown in the autocomplete and tagify, IE: "My label"
   *   - 'info_label':
   *     The extra information to be shown below the entity label.
   *   - 'avatar':
   *     The user image.
   *   - 'attributes':
   *     A key-value array of extra properties sent directly to tagify, IE:
   *     ['--tag-bg' => '#FABADA']
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildTagifyUserListItem(string $target_type, string $entity_id, string $label, ?string $info_label, string $image, string $image_style): array {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($target_type)->load($entity_id);
    $tagify_user_list_path = $this->moduleHandler->getModuleList()['tagify_user_list']->getPath();
    // Check if the label contains HTML and remove it.
    $label = $this->isHtml($label) ? strip_tags($label) : $label;

    return [
      'entity_id' => $entity_id,
      'label' => Html::decodeEntities($label),
      'info_label' => $info_label,
      'avatar' => _tagify_user_list_image_path($entity, $image, $image_style) ?: '/' . $tagify_user_list_path . '/images/no-user.svg',
      'editable' => FALSE,
    ];
  }

  /**
   * Checks if a string contains HTML code.
   *
   * @param string $string
   *   The string to be checked.
   *
   * @return bool
   *   TRUE if the string contains HTML.
   */
  protected function isHtml(string $string): bool {
    return $string !== strip_tags($string);
  }

}
