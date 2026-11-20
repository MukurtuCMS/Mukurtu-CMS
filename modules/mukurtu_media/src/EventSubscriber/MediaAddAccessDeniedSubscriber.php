<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_media\MediaCreateNotice;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Says why the add media pages are closed to someone.
 *
 * Without a cultural protocol that lets the person add media, the add media
 * pages are denied. The page stays denied; this adds a warning that explains
 * why, which the access denied page shows with its other messages.
 */
class MediaAddAccessDeniedSubscriber implements EventSubscriberInterface {

  /**
   * The routes this explains.
   */
  const ROUTES = ['entity.media.add_page', 'entity.media.add_form'];

  public function __construct(
    protected MediaCreateNotice $notice,
    protected AccountInterface $currentUser,
    protected RouteMatchInterface $routeMatch,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before core renders the access denied page, which starts at -50.
    return [
      KernelEvents::EXCEPTION => ['onException', 0],
    ];
  }

  /**
   * Adds the explanation when the add media pages are denied.
   */
  public function onException(ExceptionEvent $event): void {
    if (!$event->getThrowable() instanceof AccessDeniedHttpException) {
      return;
    }
    // Only an HTML page shows the message. Anything else would leave it
    // waiting for whatever page the person opens next.
    $request = $event->getRequest();
    if ($request->getRequestFormat() !== 'html' || $request->isXmlHttpRequest()) {
      return;
    }
    if (!in_array($this->routeMatch->getRouteName(), self::ROUTES, TRUE)) {
      return;
    }
    $bundle = NULL;
    $media_type = $this->routeMatch->getRawParameter('media_type');
    if ($media_type !== NULL) {
      $bundle = [(string) $media_type];
    }
    if (!$this->notice->appliesTo($this->currentUser, $bundle)) {
      return;
    }
    $this->messenger->addWarning($this->notice->getMessage($this->currentUser));
  }

}
