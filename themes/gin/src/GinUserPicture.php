<?php

namespace Drupal\gin;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

include_once __DIR__ . '/../gin.theme';
_gin_include_theme_includes();

/**
 * User picture build callback for the gin theme.
 */
class GinUserPicture implements TrustedCallbackInterface {

  /**
   * Lazy builder callback for the user picture.
   */
  public static function build(): array {
    /** @var \Drupal\user\UserInterface $user */
    $current_user = \Drupal::currentUser();
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());
    $url = $user->toUrl();

    // If the user is anonymous, we cannot link to the user profile.
    if ($user->isAnonymous()) {
      $url = Url::fromUri('route:<nolink>');
    }

    $build = [
      '#type' => 'link',
      '#url' => $url,
      '#title' => [
        '#markup' => $user->getDisplayName(),
      ],
      '#attributes' => [
        'id' => 'toolbar-item-user-secondary',
        'class' => [
          'toolbar-icon',
          'toolbar-icon-user',
          'trigger',
          'toolbar-item',
        ],
        'role' => 'button',
      ],
    ];

    /** @var \Drupal\image\ImageStyleInterface $style */
    $style = NULL;
    try {
      $style = \Drupal::entityTypeManager()->getStorage('image_style')->load('thumbnail');
    }
    catch (PluginNotFoundException $e) {
      // The image style plugin does not exists. $style stays NULL and no user
      // picture will be added.
    }
    if ($style === NULL) {
      return ['link' => $build];
    }

    $file = $user->user_picture ? $user->user_picture->entity : NULL;
    if ($file === NULL) {
      return ['link' => $build];
    }

    $image_url = $style->buildUrl($file->getFileUri());

    $build['#attributes']['class'] = ['toolbar-item icon-user'];
    $build['#title'] = [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'src' => $image_url,
        'alt' => $user->getAccountName(),
        'class' => [
          'icon-user__image',
        ],
      ],
    ];

    return ['link' => $build];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['build'];
  }

}
