<?php

/**
 * @file
 * Token integration geolocation field type.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function geolocation_token_info() {
  if (!\Drupal::hasService('token.entity_mapper')) {
    return;
  }

  $types = [];
  $tokens = [];

  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
    if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
      continue;
    }
    $token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($entity_type_id);
    if (empty($token_type)) {
      continue;
    }

    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $fields */
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
    foreach ($fields as $field_name => $field) {
      if ($field->getType() != 'geolocation') {
        continue;
      }

      $tokens[$token_type . '-' . $field_name]['lat_sex'] = [
        'name' => t("Latitude in sexagesimal notation"),
        'description' => NULL,
        'module' => 'geolocation',
      ];
      $tokens[$token_type . '-' . $field_name]['lng_sex'] = [
        'name' => t("Longitude in sexagesimal notation"),
        'description' => NULL,
        'module' => 'geolocation',
      ];

      $tokens[$token_type . '-' . $field_name]['data'] = [
        'name' => t("Data"),
        'description' => NULL,
        'module' => 'geolocation',
      ];
    }
  }

  return [
    'types' => $types,
    'tokens' => $tokens,
  ];
}

/**
 * Implements hook_tokens().
 *
 * {@inheritdoc}
 */
function geolocation_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if (!empty($data['field_property'])) {
    foreach ($tokens as $token => $original) {
      $delta = 0;
      $parts = explode(':', $token);
      if (is_numeric($parts[0])) {
        if (count($parts) > 1) {
          $delta = $parts[0];
          $property_name = $parts[1];
        }
        else {
          continue;
        }
      }
      else {
        $property_name = $parts[0];
      }
      if (!isset($data[$data['field_name']][$delta])) {
        continue;
      }

      /** @var \Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem $item */
      $item = $data[$data['field_name']][$delta];

      switch ($property_name) {

        case 'lng_sex':
          $replacements[$original] = $item::decimalToSexagesimal($item->get('lng')->getValue());
          break;

        case 'lat_sex':
          $replacements[$original] = $item::decimalToSexagesimal($item->get('lat')->getValue());
          break;

        case 'data':
          // Handle data tokens.
          $metadata = $item->get('data')->getValue();
          if (is_array($metadata) || ($metadata instanceof \Traversable)) {
            foreach ($metadata as $key => $value) {
              try {
                // Maybe there is values inside the values.
                if (is_array($value) || ($value instanceof \Traversable)) {
                  foreach ($value as $deepkey => $deepvalue) {
                    if (is_string($deepvalue)) {
                      $replacements[$token . ':' . $key . ':' . $deepkey . ']'] = (string) $deepvalue;
                    }
                  }
                }
                else {
                  $replacements[$token . ':' . $key . ']'] = (string) $value;
                }
              }
              catch (\Exception $e) {
                \Drupal::logger('geolocation')->alert($e->getMessage());
              }
            }
          }
          break;

      }
    }
  }

  return $replacements;
}
