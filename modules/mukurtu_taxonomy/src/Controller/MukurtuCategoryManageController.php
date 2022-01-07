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

      // Render the form to add a new category.
      $newCategoryTerm = Term::create([
        'vid' => $vocabulary->id(),
      ]);

      $form = $this->entityTypeManager()
        ->getFormObject('taxonomy_term', 'default')
        ->setEntity($newCategoryTerm);

      $build['add_category'] = [
        '#type' => 'details',
        '#title' => $this->t('Add a new category'),
      ];

      $build['add_category']['form'] = $this->formBuilder()->getForm($form);
    }

    return $build;
  }

}
