<?php

namespace Drupal\geolocation_gpx\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * Plugin implementation of the 'geolocation_gpx_file' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_gpx_file",
 *   label = @Translation("Geolocation GPX File"),
 *   field_types = {
 *     "geolocation_gpx_file"
 *   }
 * )
 */
class GeolocationGpxFileWidget extends FileWidget {

  /**
   * {@inheritdoc}
   */
  public static function submit($form, FormStateInterface $form_state) {
    parent::submit($form, $form_state);

    // During the form rebuild, formElement() will create field item widget
    // elements using re-indexed deltas, so clear out FormState::$input to
    // avoid a mismatch between old and new deltas. The rebuilt elements will
    // have #default_value set appropriately for the current state of the field,
    // so nothing is lost in doing this.
    $button = $form_state->getTriggeringElement();
    $parents = array_slice($button['#parents'], 0, -2);
    NestedArray::setValue($form_state->getUserInput(), $parents, NULL);

    $submitted_values = NestedArray::getValue($form_state->getValues(), array_slice($button['#parents'], 0, -2));
    foreach ($submitted_values as $delta => $submitted_value) {
      foreach ($submitted_value['fids'] as $fid) {
        /** @var \Drupal\file\FileInterface $file */
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
      }
    }
  }

}
