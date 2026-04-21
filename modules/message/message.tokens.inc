<?php

/**
 * @file
 * Builds placeholder replacement tokens for message-related data.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\Entity\User;

/**
 * Implements hook_token_info().
 */
function message_token_info() {
  // @todo Remove this token in Message 2.0.
  $message['author'] = [
    'name' => t("Author (deprecated)"),
    'type' => 'user',
  ];

  return [
    'tokens' => ['message' => $message],
  ];
}

/**
 * Implements hook_tokens().
 */
function message_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $token_service = \Drupal::token();

  $replacements = [];

  if ($type == 'message' && !empty($data['message'])) {
    /** @var \Drupal\message\Entity\Message $message */
    $message = $data['message'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        // @todo BC token. Remove in Message 2.0.
        case 'author':
          $account = $message->getOwner() ? $message->getOwner() : User::load(0);
          $replacements[$original] = $account->label();
          @trigger_error('The "message:author" token is deprecated. Please use "message:uid:entity" offered by Token module in its place.');
          break;
      }
    }

    if ($author_tokens = $token_service->findWithPrefix($tokens, 'author')) {
      $replacements += $token_service->generate('user', $author_tokens, ['user' => $message->getOwner()], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}
