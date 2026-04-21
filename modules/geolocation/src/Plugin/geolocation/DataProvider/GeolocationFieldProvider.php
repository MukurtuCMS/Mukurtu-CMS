<?php

namespace Drupal\geolocation\Plugin\geolocation\DataProvider;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\geolocation\DataProviderBase;
use Drupal\geolocation\DataProviderInterface;
use Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Provides default geolocation field.
 *
 * @DataProvider(
 *   id = "geolocation_field_provider",
 *   name = @Translation("Geolocation Field"),
 *   description = @Translation("Geolocation Field."),
 * )
 */
class GeolocationFieldProvider extends DataProviderBase implements DataProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getTokenHelp(FieldDefinitionInterface $fieldDefinition = NULL) {

    $element = parent::getTokenHelp($fieldDefinition);

    $element['token_items'][] = [
      'token' => [
        '#plain_text' => '[geolocation_current_item:lat_sex]',
      ],
      'description' => [
        '#plain_text' => $this->t('Latitude value in sexagesimal notation'),
      ],
    ];

    $element['token_items'][] = [
      'token' => [
        '#plain_text' => '[geolocation_current_item:lng_sex]',
      ],
      'description' => [
        '#plain_text' => $this->t('Longitude value in sexagesimal notation'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function replaceFieldItemTokens($text, FieldItemInterface $fieldItem) {
    $token_context['geolocation_current_item'] = $fieldItem;

    $text = \Drupal::token()->replace($text, $token_context, [
      'callback' => [$this, 'geolocationItemTokens'],
      'clear' => FALSE,
    ]);

    return parent::replaceFieldItemTokens($text, $fieldItem);
  }

  /**
   * {@inheritdoc}
   */
  public function geolocationItemTokens(array &$replacements, array $data, array $options) {
    if (isset($data['geolocation_current_item'])) {

      /** @var \Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem $item */
      $item = $data['geolocation_current_item'];

      $replacements['[geolocation_current_item:lat_sex]'] = GeolocationItem::decimalToSexagesimal($item->get('lat')->getValue());
      $replacements['[geolocation_current_item:lng_sex]'] = GeolocationItem::decimalToSexagesimal($item->get('lng')->getValue());

      // Handle data tokens.
      $metadata = $item->get('data')->getValue();
      if (is_array($metadata) || ($metadata instanceof \Traversable)) {
        foreach ($metadata as $key => $value) {
          try {
            // Maybe there is values inside the values.
            if (is_array($value) || ($value instanceof \Traversable)) {
              foreach ($value as $deepkey => $deepvalue) {
                if (is_string($deepvalue)) {
                  $replacements['[geolocation_current_item:data:' . $key . ':' . $deepkey . ']'] = (string) $deepvalue;
                }
              }
            }
            else {
              $replacements['[geolocation_current_item:data:' . $key . ']'] = (string) $value;
            }
          }
          catch (\Exception $e) {
            \Drupal::logger('geolocation')->warning($e->getMessage());
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isViewsGeoOption(FieldPluginBase $views_field) {
    return ($views_field->getPluginId() == 'geolocation_field');
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition) {
    return ($fieldDefinition->getType() == 'geolocation');
  }

  /**
   * {@inheritdoc}
   */
  public function getPositionsFromItem(FieldItemInterface $fieldItem) {
    if ($fieldItem instanceof GeolocationItem) {
      return [
        [
          'lat' => $fieldItem->get('lat')->getValue(),
          'lng' => $fieldItem->get('lng')->getValue(),
        ],
      ];
    }

    return FALSE;
  }

}
