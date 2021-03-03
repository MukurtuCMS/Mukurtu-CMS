<?php

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;

class MukurtuManageTaxonomyController extends ControllerBase {

  /**
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access($taxonomy_vocabulary) {
    $account = \Drupal::currentUser();

    if ($account->hasPermission("create terms in $taxonomy_vocabulary") && $account->hasPermission("edit terms in $taxonomy_vocabulary")) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Display the manage categories page.
   */
  public function content($taxonomy_vocabulary) {
    $build = [];
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($taxonomy_vocabulary);

    if ($vocabulary) {
      // Render the taxonomy overview form.
      $build[] = \Drupal::formBuilder()->getForm('Drupal\taxonomy\Form\OverviewTerms', $vocabulary);

      // Render the form to add a new term.
      $newTerm = Term::create([
        'vid' => $vocabulary->id(),
      ]);
      $form = \Drupal::service('entity.manager')
        ->getFormObject('taxonomy_term', 'default')
        ->setEntity($newTerm);

      $build['add_term'] = [
        '#type' => 'details',
        '#title' => $this->t('Add a new term in %vocabulary', ['%vocabulary' => $vocabulary->get('name')]),
      ];
      $build['add_term']['form'] = \Drupal::formBuilder()->getForm($form);
    }

    return $build;
  }

  public function addPage() {
    $build = [
      '#theme' => 'mukurtu_vocabulary_add_list',
      '#cache' => [
        'tags' => $this->entityTypeManager()->getDefinition('taxonomy_vocabulary')->getListCacheTags(),
      ],
    ];

    $content = [];

    // Only use node types the user has access to.
    foreach ($this->entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple() as $type) {
      $access = $this->entityTypeManager()->getAccessControlHandler('taxonomy_vocabulary')->createAccess($type->id(), NULL, [], TRUE);
      if ($access->isAllowed()) {
        $content[$type->id()] = $type;
      }
    }

    // Bypass the node/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('mukurtu_taxonomy.manage_taxonomy_vocabulary', ['taxonomy_vocabulary' => $type->id()]);
    }

    $build['#content'] = $content;

    return $build;
  }

}
