<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface defining generate thumbnail operations.
 */
interface MukurtuThumbnailGenerationInterface
{
  /**
   * Thumbnail generation method that bundle classes will override.
   */
  public function generateThumbnail(&$element, FormStateInterface $form_state, &$complete_form);
}
