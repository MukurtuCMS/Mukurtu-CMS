<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\mukurtu_core\Form\CharacterPaletteSettingsForm;

/**
 * Marks text inputs for the character palette (#2560).
 *
 * The palette itself is js/character-palette.js. Server side, this only
 * decides whether the palette is on, marks each text field, text area and
 * search box with a data attribute, and hands the script its characters.
 * Email, password, URL, number and phone inputs are their own element types,
 * so they never reach the #process callback.
 */
final class CharacterPalette {

  /**
   * The data attribute the script looks for.
   */
  public const ATTRIBUTE = 'data-mukurtu-character-palette';

  /**
   * Element types that get the palette.
   */
  public const ELEMENT_TYPES = [
    'textfield',
    'textarea',
    'search',
    // Their own element types, so they don't inherit textfield's callback:
    // core's entity autocomplete, and the Tagify fields used for keywords,
    // locations and people (#2561). Types from a module that isn't installed
    // are skipped by hook_element_info_alter().
    'entity_autocomplete',
    'entity_autocomplete_tagify',
  ];

  /**
   * Returns the languages that have characters, as the script expects them.
   *
   * Read through the config factory, so a translated site gets its language
   * and character names in the current language.
   *
   * @return array
   *   A list of ['label' => string, 'characters' => [['character', 'name']]],
   *   empty when the palette is off or has no characters.
   */
  public static function groups(): array {
    $config = \Drupal::config(CharacterPaletteSettingsForm::SETTINGS);
    if (!$config->get('enabled')) {
      return [];
    }
    $groups = [];
    foreach ($config->get('groups') ?? [] as $group) {
      if (!empty($group['characters'])) {
        $groups[] = [
          'label' => (string) $group['label'],
          'characters' => array_values($group['characters']),
        ];
      }
    }
    return $groups;
  }

  /**
   * Cacheability of anything that depends on the palette's settings.
   */
  public static function cacheability(): BubbleableMetadata {
    return (new BubbleableMetadata())
      ->addCacheableDependency(\Drupal::config(CharacterPaletteSettingsForm::SETTINGS));
  }

  /**
   * Element #process callback: marks a text input for the palette.
   *
   * An element can opt out with '#character_palette' => FALSE.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $cacheability = static::cacheability();
    if (($element['#character_palette'] ?? TRUE) !== FALSE && static::groups() !== []) {
      $element['#attributes'][static::ATTRIBUTE] = static::label($element);
      $element['#attached']['library'][] = 'mukurtu_core/character_palette';
    }
    $cacheability->merge(BubbleableMetadata::createFromRenderArray($element))->applyTo($element);
    return $element;
  }

  /**
   * The field's name for the palette button, "Characters for @field".
   *
   * Falls back to the placeholder, then to an empty string, in which case
   * the script names the button "Characters" alone.
   */
  protected static function label(array $element): string {
    foreach (['#title', '#placeholder'] as $key) {
      if (isset($element[$key]) && !is_array($element[$key])) {
        $label = trim(Html::decodeEntities(strip_tags((string) $element[$key])));
        if ($label !== '') {
          return $label;
        }
      }
    }
    if (isset($element['#attributes']['aria-label']) && is_string($element['#attributes']['aria-label'])) {
      return $element['#attributes']['aria-label'];
    }
    return '';
  }

}
