<?php

namespace Drupal\facets\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityForm;
use Drupal\facets\FacetSourceInterface;

/**
 * Provides route responses for facet source configuration.
 */
class FacetSourceController extends ControllerBase {

  /**
   * Configuration for the facet source.
   *
   * @param string $facets_facet_source
   *   The plugin id.
   *
   * @return array
   *   A renderable array containing the form.
   */
  public function facetSourceConfigForm($facets_facet_source) {
    // Returns the render array of the FacetSourceConfigForm.
    $form_object = $this->entityTypeManager()->getFormObject('facets_facet_source', 'edit');
    assert($form_object instanceof EntityForm);

    $facet_source_storage = $this->entityTypeManager()->getStorage('facets_facet_source');
    $source_id = str_replace(':', '__', $facets_facet_source);
    $facet_source = $facet_source_storage->load($source_id);

    if (!$facet_source instanceof FacetSourceInterface) {
      $facet_source = $facet_source_storage->create([
        'id' => $source_id,
        'name' => $facets_facet_source,
      ]);
      $facet_source->save();
    }
    $form_object->setEntity($facet_source);

    return $this->formBuilder()->getForm($form_object);
  }

}
