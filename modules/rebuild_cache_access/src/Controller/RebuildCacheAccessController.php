<?php

namespace Drupal\rebuild_cache_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for rebuild_cache_access module routes.
 */
class RebuildCacheAccessController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger channel for rebuild_cache_access.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a RebuildCacheAccessController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for rebuild_cache_access.
   */
  public function __construct(RequestStack $request_stack, ModuleHandlerInterface $module_handler, LoggerInterface $logger) {
    $this->requestStack = $request_stack;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('module_handler'),
      $container->get('logger.channel.rebuild_cache_access')
    );
  }

  /**
   * Reload the previous page.
   */
  public function reloadPage() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request->server->get('HTTP_REFERER')) {
      return $request->server->get('HTTP_REFERER');
    }
    else {
      return '/';
    }
  }

  /**
   * Rebuild all caches, then redirects to the previous page.
   */
  public function rebuildCache() {
    if ($this->moduleHandler->moduleExists('views')) {
      views_invalidate_cache();
    }
    drupal_flush_all_caches();
    $this->messenger()->addMessage($this->t('All caches cleared.'));
    $this->logger->info($this->t('All caches cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

}
