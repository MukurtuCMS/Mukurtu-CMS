<?php

declare(strict_types=1);

namespace Drupal\geocoder\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for Geocoder provider add forms.
 */
class GeocoderProviderAddForm extends GeocoderProviderFormBase {

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string|null $geocoder_provider_id
   *   The Geocoder provider ID or NULL if not provided.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $geocoder_provider_id = NULL): array {
    if (isset($geocoder_provider_id)) {
      $this->entity->setPlugin($geocoder_provider_id);
    }

    // Derive the label and type from the Geocoder provider definition.
    $definition = $this->entity->getPluginDefinition();
    if (isset($definition['name'])) {
      $this->entity->set('label', $definition['name']);
    }

    return parent::buildForm($form, $form_state);
  }

}
