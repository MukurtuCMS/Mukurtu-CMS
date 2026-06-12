<?php

namespace Drupal\mukurtu_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for toggling media published status.
 */
class MediaStatusController extends ControllerBase {

  public function publish(MediaInterface $media, Request $request): RedirectResponse {
    if (!$media->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $media->setPublished()->save();
    $this->messenger()->addStatus($this->t('%label has been published.', ['%label' => $media->label()]));
    $destination = $request->query->get('destination', '/admin/content/media');
    return new RedirectResponse($destination);
  }

  public function unpublish(MediaInterface $media, Request $request): RedirectResponse {
    if (!$media->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $media->setUnpublished()->save();
    $this->messenger()->addStatus($this->t('%label has been unpublished.', ['%label' => $media->label()]));
    $destination = $request->query->get('destination', '/admin/content/media');
    return new RedirectResponse($destination);
  }

}
