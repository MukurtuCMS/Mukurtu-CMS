<?php

declare(strict_types=1);

namespace Drupal\gin_lb\Service;

/**
 * Provides an interface for context validator service.
 */
interface ContextValidatorInterface {

  /**
   * Check if the current theme is not Gin or inherit from Gin.
   *
   * @return bool
   *   True if not Gin based.
   */
  public function isValidTheme(): bool;

  /**
   * Returns true if the given form id should rendered in Gin style.
   *
   * @param string $form_id
   *   The form id.
   * @param array $form
   *   The form.
   *
   * @return bool
   *   True for Gin form.
   */
  public function isLayoutBuilderFormId(string $form_id, array $form): bool;

  /**
   * Check if the current route is a Layout Builder route.
   *
   * @return bool
   *   True for Layout Builder routes.
   */
  public function isLayoutBuilderRoute(): bool;

}
