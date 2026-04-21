<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;

/**
 * Provides a mock URL object.
 */
class TestUrl extends Url {

  /**
   * Constructs a new class instance.
   *
   * @param string $path
   *   The internal path for this URL.
   */
  public function __construct(string $path) {
    $this->internalPath = $path;
  }

  /**
   * {@inheritdoc}
   */
  public function toString($collect_bubbleable_metadata = FALSE) {
    $url = $this->internalPath;
    if (!empty($this->options['absolute'])) {
      $url = 'http://www.example.com' . $url;
    }
    if ($collect_bubbleable_metadata) {
      return (new GeneratedUrl())->setGeneratedUrl($url);
    }
    return $url;
  }

}
