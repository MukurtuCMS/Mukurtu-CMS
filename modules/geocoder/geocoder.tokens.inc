<?php

/**
 * @file
 * Token definitions for the Geocoder module.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function geocoder_token_info(): array {
  return [
    'types' => [
      'geocoder' => [
        'name' => t('Geocoder'),
        'description' => t('Tokens provided by the Geocoder module.'),
      ],
    ],
    'tokens' => [
      'geocoder' => [
        'site-uuid' => [
          'name' => t('Site UUID'),
          'description' => t('The unique identifier (UUID) of the site, from system.site configuration.'),
        ],
      ],
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function geocoder_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];

  if ($type === 'geocoder' && !empty($data['geocoder'])) {
    foreach ($tokens as $name => $original) {
      if ($name === 'site-uuid' && isset($data['geocoder']['site_uuid'])) {
        $replacements[$original] = $data['geocoder']['site_uuid'];
      }
    }
  }

  return $replacements;
}
