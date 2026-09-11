<?php

declare(strict_types=1);

namespace Drupal\mukurtu_footer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects to the footer's block_content edit form.
 *
 * The footer's block_content entity ID is not guaranteed to be 1 (or any
 * other fixed value), so this looks it up rather than linking to a
 * hardcoded path.
 */
class FooterEditRedirectController extends ControllerBase {

  /**
   * Redirects to the footer block_content entity's edit form.
   */
  public function edit(): RedirectResponse {
    $storage = $this->entityTypeManager()->getStorage('block_content');
    $ids = $storage->getQuery()
      ->condition('type', 'mukurtu_footer')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      $this->messenger()->addWarning($this->t('No footer content was found to edit.'));
      $url = Url::fromRoute('entity.block_content.collection');
    }
    else {
      $url = Url::fromRoute('entity.block_content.edit_form', ['block_content' => reset($ids)]);
    }

    return new RedirectResponse($url->toString());
  }

}
