<?php

/**
 * @file
 * Theme functions for the media_entity_soundcloud module.
 */

use Drupal\Core\Url;

/**
 * Prepares variables for media_entity_soundcloud templates.
 *
 * Default template: media-soundcloud-embed.html.twig.
 */
function template_preprocess_media_soundcloud_embed(&$variables) {
  // Build the embed url.
  $uri = 'https://w.soundcloud.com/player/';

  // Build the query.
  $query = [
    'url' => 'https://api.soundcloud.com/' . $variables['source_id'],
  ];

  // Add visual.
  $query['visual'] = ($variables['type'] == 'visual');

  // Add options.
  foreach ($variables['options'] as $option => $value) {
    $query[$option] = $value == '0' ? 'false' : 'true';
  }

  // Add color.
  if (!empty($variables['color'])) {
    $query['color'] = $variables['color'];
  }

  // Add secret_token for private URLs
  if(!empty($variables['secret_token'])) {
    $query['secret_token'] = $variables['secret_token'];
  }

  $url = Url::fromUri($uri, ['query' => $query]);
  $variables['url'] = $url->toString();
}
