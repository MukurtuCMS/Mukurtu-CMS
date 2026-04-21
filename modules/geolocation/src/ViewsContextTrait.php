<?php

namespace Drupal\geolocation;

/**
 * Trait ViewsContext.
 */
trait ViewsContextTrait {

  /**
   * Get display handler from context.
   *
   * @param mixed $context
   *   Context.
   *
   * @return bool|\Drupal\views\Plugin\views\display\DisplayPluginBase
   *   Display handler or FALSE.
   */
  protected static function getViewsDisplayHandler($context = NULL) {
    if (!is_object($context)) {
      return FALSE;
    }

    if (is_subclass_of($context, 'Drupal\views\Plugin\views\style\StylePluginBase')) {
      /** @var \Drupal\views\Plugin\views\display\DisplayPluginBase $context */
      return $context->displayHandler;
    }

    if (is_subclass_of($context, 'Drupal\views\Plugin\views\HandlerBase')) {
      /** @var \Drupal\views\Plugin\views\display\DisplayPluginBase $context */
      return $context->displayHandler;
    }

    return FALSE;
  }

}
