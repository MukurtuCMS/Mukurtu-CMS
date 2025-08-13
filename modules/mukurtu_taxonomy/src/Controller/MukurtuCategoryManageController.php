<?php

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;

class MukurtuCategoryManageController extends ControllerBase {

  /**
   * Display the manage categories page.
   */
  public function content() {
    $build = [];
    $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load('category');

    if ($vocabulary) {
      // Render the taxonomy overview form.
      $build[] = $this->formBuilder()->getForm('Drupal\taxonomy\Form\OverviewTerms', $vocabulary);

    }

    return $build;
  }

}
