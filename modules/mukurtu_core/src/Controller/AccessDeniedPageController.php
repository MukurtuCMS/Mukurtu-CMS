<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the site's custom 403 (access denied) page.
 */
class AccessDeniedPageController extends ControllerBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_core.access_denied';

  /**
   * Title callback: returns the manager-configurable page title.
   */
  public function getTitle() {
    $title = $this->config(static::SETTINGS)->get('title');
    return $title ?: $this->t('Incorrect Permissions');
  }

  /**
   * Builds the access denied page content.
   */
  public function build() {
    $config = $this->config(static::SETTINGS);

    // getDestinationArray() reads the 'destination' query parameter that
    // core's CustomPageExceptionHtmlSubscriber already attached when it
    // internally forwarded here from the page the user originally tried to
    // reach. That value is already core-validated (rejects external/absolute
    // URLs), so building the login link from it carries no open redirect
    // risk.
    $login_url = Url::fromRoute('user.login', [], [
      'query' => $this->getDestinationArray(),
    ]);

    // Fixed sentence -- always rendered, never editable via the settings
    // form.
    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('If you followed a link here, <a href=":login_url">log in</a> and try again.', [
        ':login_url' => $login_url->toString(),
      ]),
    ];

    // Editable message body.
    $message = $config->get('message') ?? [];
    if (!empty($message['value'])) {
      $build['message'] = [
        '#type' => 'processed_text',
        '#text' => $message['value'],
        '#format' => $message['format'] ?? 'full_html',
      ];
    }

    $build['#cache']['contexts'][] = 'url.query_args:destination';
    $build['#cache']['tags'] = $config->getCacheTags();

    return $build;
  }

}
