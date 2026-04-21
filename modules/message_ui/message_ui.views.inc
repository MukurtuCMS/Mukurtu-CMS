<?php

/**
 * @file
 * Integration with the views module.
 */

/**
 * Implements hook_views_data().
 */
function message_ui_views_data() {

  $data = [];

  $data['message']['message_ui_contextual_links'] = [
    'field' => [
      'title' => t('Contextual links'),
      'help' => t('The aliased path to this content.'),
      'id' => 'message_ui_contextual_links',
    ],
  ];

  return $data;
}
