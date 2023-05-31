<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface defining generate thumbnail operations.
 */
interface MukurtuThumbnailGenerationInterface
{
  /**
   * Method to generate a thumbnail based on the uploaded media asset.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @param $complete_form
   *   The complete form structure.
   *
   * @return string|null
   *   The file id of the generated thumbnail (e.g. "4") if generation was
   *   successful, NULL otherwise.
   */
  public function generateThumbnail(&$element, FormStateInterface $form_state, &$complete_form);

  /**
   * Checks based on the media bundle if the triggering element is the media
   * upload button.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @param $triggeringElementName
   *   Name of the triggering element in form state.
   *
   * @return bool
   *   True if the media upload button is the triggering element, false
   *   otherwise.
   */
  public function mediaUploadIsTriggeringElement(FormStateInterface $form_state, $triggeringElementName);

  /**
   * Retrieves the default thumbnail of the media bundle, if set.
   *
   * @return string|null
   *   The file id of the default thumbnail (e.g. "4") if set, NULL otherwise.
   */
  public function getDefaultThumbnail();

  /**
   * Checks if the user has uploaded a media file.
   *
   * @return bool
   *   True if the media item contains a media file, false otherwise.
   */
  public function hasUploadedMediaFile();

  /**
   * Retrieves the filename of the media file.
   *
   * @return string|null
   *   The name of the media file if set, NULL otherwise.
   */
  public function getMediaFilename();
}
