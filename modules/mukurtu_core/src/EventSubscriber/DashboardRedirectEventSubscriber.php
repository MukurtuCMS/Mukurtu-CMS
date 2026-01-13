<?php

namespace Drupal\mukurtu_core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DashboardRedirectEventSubscriber implements EventSubscriberInterface
{
  // Redirect all requests to the old dashboard (at /dashboard) to the new one
  // (at /dashboard/mukurtu_dashboard).
  public function onRequest(RequestEvent $event)
  {
    $current_path = \Drupal::request()->getPathInfo();
    if ($current_path == '/dashboard') {
      $to_url = Url::fromUserInput('/dashboard/mukurtu_dashboard');
      $response = new RedirectResponse($to_url->toString());
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }
}
