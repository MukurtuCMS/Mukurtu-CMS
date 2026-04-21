<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\gin_lb\Service\ContextValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class PageAttachments implements ContainerInjectionInterface {

  /**
   * The context validator.
   *
   * @var \Drupal\gin_lb\Service\ContextValidatorInterface
   */
  protected ContextValidatorInterface $contextValidator;

  /**
   * Constructor.
   */
  public function __construct(
    ContextValidatorInterface $contextValidator,
    protected ConfigFactoryInterface $configFactory,
    protected ThemeManagerInterface $themeManager,
  ) {
    $this->contextValidator = $contextValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('gin_lb.context_validator'),
      $container->get('config.factory'),
      $container->get('theme.manager'),
    );
  }

  /**
   * Hook implementation.
   *
   * @param array &$attachments
   *   An array that you can add attachments to.
   */
  public function attachments(array &$attachments): void {
    if (!$this->contextValidator->isLayoutBuilderRoute()) {
      return;
    }

    $attachments['#attached']['library'][] = 'gin_lb/gin_lb_init';
    $attachments['#attached']['library'][] = 'gin_lb/offcanvas';
    $attachments['#attached']['library'][] = 'gin_lb/preview';
    $attachments['#attached']['library'][] = 'gin_lb/toolbar';
    $attachments['#attached']['library'][] = 'gin/gin_ckeditor';
    $attachments['#attached']['library'][] = 'claro/claro.jquery.ui';
    $attachments['#attached']['library'][] = 'gin_lb/gin_lb';
    $attachments['#attached']['library'][] = 'claro/global-styling';
    if (\Drupal::VERSION >= '10.0.0') {
      $attachments['#attached']['library'][] = 'gin_lb/gin_lb_10';
    }

    $active_theme = $this->themeManager->getActiveTheme();
    $theme_name = $active_theme->getName();
    if (str_contains($theme_name, 'olivero')) {
      $attachments['#attached']['library'][] = 'gin_lb/olivero';
    }

    if ($this->configFactory->get('gin_lb.settings')->get('toastify_loading') !== 'custom') {
      $attachments['#attached']['library'][] = 'gin_lb/gin_lb_toastify';
    }
  }

}
