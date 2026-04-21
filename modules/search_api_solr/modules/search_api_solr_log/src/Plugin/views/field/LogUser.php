<?php

namespace Drupal\search_api_solr_log\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Provides a field handler that renders a log event with replaced variables.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("search_api_solr_log_user")]
class LogUser extends FieldPluginBase {

  /**
   * Renders the log level value as a string.
   */
  public function render($values): string {
    $user_id = $this->getValue($values)[0] ?? 0;

    // Load the user entity.
    $user = User::load($user_id);

    if ($user) {
      // Generate the URL to the user profile.
      $url = Url::fromRoute('entity.user.canonical', ['user' => $user_id]);
      return Link::fromTextAndUrl($user->getDisplayName(), $url)->toString();
    }

    return $this->t('Unknown User')->render();
  }

}
