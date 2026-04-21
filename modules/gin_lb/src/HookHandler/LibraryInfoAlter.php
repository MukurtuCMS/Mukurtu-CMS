<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class LibraryInfoAlter implements ContainerInjectionInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
  ) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Hook implementation.
   *
   * @param array &$libraries
   *   An array that you can add libraries dependencies to.
   */
  public function alter(array &$libraries): void {
    $toastify_loading = $this->configFactory->get('gin_lb.settings')->get('toastify_loading');
    if ($toastify_loading === 'cdn') {
      $libraries['gin_lb_toastify']['dependencies'][] = 'gin_lb/toastify_cdn';
    }
    elseif ($toastify_loading === 'composer') {
      $libraries['gin_lb_toastify']['dependencies'][] = 'gin_lb/toastify_composer';
    }
  }

}
