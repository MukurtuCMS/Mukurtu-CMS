<?php

namespace Drupal\twig_tweak_test\Controller;

/**
 * Returns responses for Twig Tweak Test routes.
 */
final class TwigTweakTestController {

  /**
   * Builds the response.
   */
  public function build(): array {
    return ['#theme' => 'twig_tweak_test'];
  }

}
