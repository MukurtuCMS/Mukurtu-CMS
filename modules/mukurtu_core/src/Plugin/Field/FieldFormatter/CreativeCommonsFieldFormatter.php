<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Field\FieldFilteredMarkup;

/**
 * Plugin implementation of the 'mukurtu_creative_commons_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "mukurtu_creative_commons_formatter",
 *   module = "mukurtu_core",
 *   label = @Translation("Creative Commons Formatter"),
 *   field_types = {
 *     "list_string"
 *   }
 * )
 */
class CreativeCommonsFieldFormatter extends FormatterBase {
  const LICENSEHTML = [
    'http://creativecommons.org/licenses/by/4.0' => '<p xmlns:cc="http://creativecommons.org/ns#" >This work is licensed under <a href="http://creativecommons.org/licenses/by/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution 4.0 International<span><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"></a></p>',
    'http://creativecommons.org/licenses/by-nc/4.0' => '<p xmlns:cc="http://creativecommons.org/ns#" >This work is licensed under <a href="http://creativecommons.org/licenses/by-nc/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution-NonCommercial 4.0 International<span><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/nc.svg?ref=chooser-v1"></span></a></p>',
    'http://creativecommons.org/licenses/by-sa/4.0' => '<p xmlns:cc="http://creativecommons.org/ns#" >This work is licensed under <a href="http://creativecommons.org/licenses/by-sa/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution-ShareAlike 4.0 International<span><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/sa.svg?ref=chooser-v1"></span></a></p>',
    'http://creativecommons.org/licenses/by-nc-sa/4.0' => '<p xmlns:cc="http://creativecommons.org/ns#" >This work is licensed under <a href="http://creativecommons.org/licenses/by-nc-sa/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution-NonCommercial-ShareAlike 4.0 International<span><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/nc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/sa.svg?ref=chooser-v1"><span></a></p>',
    'http://creativecommons.org/licenses/by-nd/4.0' => '<p xmlns:cc="http://creativecommons.org/ns#" >This work is licensed under <a href="http://creativecommons.org/licenses/by-nd/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution-NoDerivatives 4.0 International<span><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/nd.svg?ref=chooser-v1"></span></a></p>',
    'http://creativecommons.org/licenses/by-nc-nd/4.0' => '<p xmlns:cc="http://creativecommons.org/ns#" >This work is licensed under <a href="http://creativecommons.org/licenses/by-nc-nd/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution-NonCommercial-NoDerivatives 4.0 International<span><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/nc.svg?ref=chooser-v1"><img alt="" src="https://mirrors.creativecommons.org/presskit/icons/nd.svg?ref=chooser-v1"></span></a></p>',
  ];

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Only collect allowed options if there are actually items to display.
    if ($items->count()) {
      $provider = $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getOptionsProvider('value', $items->getEntity());
      // Flatten the possible options, to support opt groups.
      $options = OptGroup::flattenOptions($provider->getPossibleOptions());

      foreach ($items as $delta => $item) {
        $value = $item->value;
        // If the stored value is in the current set of allowed values, display
        // the associated label, otherwise just display the raw value.
        $output = self::LICENSEHTML[$value] ?? $value;
        $elements[$delta] = [
          '#markup' => $output,
          '#allowed_tags' => FieldFilteredMarkup::allowedTags(),
        ];
      }
    }

    return $elements;
  }

}
