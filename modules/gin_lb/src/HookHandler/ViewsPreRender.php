<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\gin_lb\Service\ContextValidatorInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class ViewsPreRender implements ContainerInjectionInterface {

  /**
   * The context validator.
   *
   * @var \Drupal\gin_lb\Service\ContextValidatorInterface
   */
  protected ContextValidatorInterface $contextValidator;

  /**
   * Constructor.
   *
   * @param \Drupal\gin_lb\Service\ContextValidatorInterface $contextValidator
   *   The context validator.
   */
  public function __construct(
    ContextValidatorInterface $contextValidator,
  ) {
    $this->contextValidator = $contextValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('gin_lb.context_validator')
    );
  }

  /**
   * Alter media library view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object about to be processed.
   */
  public function preRender(ViewExecutable $view): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    if ($view->id() != 'media_library') {
      return;
    }

    if ($view->display_handler->options['defaults']['css_class']) {
      $this->addClassToOption($view->displayHandlers->get('default')->options['css_class'], ['media-library-view']);
    }
    else {
      $this->addClassToOption($view->display_handler->options['css_class'], ['media-library-view']);
    }

    if ($view->current_display === 'page') {
      if (\array_key_exists('media_bulk_form', $view->field)) {
        $this->addClassToOption($view->field['media_bulk_form']->options['element_class'], ['media-library-item__click-to-select-checkbox']);
      }
      if (\array_key_exists('rendered_entity', $view->field)) {
        $this->addClassToOption($view->field['rendered_entity']->options['element_class'], ['media-library-item__content']);
      }
      if (\array_key_exists('edit_media', $view->field)) {
        $this->addClassToOption($view->field['edit_media']->options['alter']['link_class'], ['media-library-item__edit']);
        $this->addClassToOption($view->field['edit_media']->options['alter']['link_class'], ['icon-link']);
      }
      if (\array_key_exists('delete_media', $view->field)) {
        $this->addClassToOption($view->field['delete_media']->options['alter']['link_class'], ['media-library-item__remove']);
        $this->addClassToOption($view->field['delete_media']->options['alter']['link_class'], ['icon-link']);
      }
    }
    elseif (\strpos($view->current_display, 'widget') === 0) {
      if (\array_key_exists('rendered_entity', $view->field)) {
        $this->addClassToOption($view->field['rendered_entity']->options['element_class'], ['media-library-item__content']);
      }
      if (\array_key_exists('media_library_select_form', $view->field)) {
        $this->addClassToOption($view->field['media_library_select_form']->options['element_wrapper_class'], ['media-library-item__click-to-select-checkbox']);
      }

      if ($view->display_handler->options['defaults']['css_class']) {
        $this->addClassToOption($view->displayHandlers->get('default')->options['css_class'], ['media-library-view--widget']);
      }
      else {
        $this->addClassToOption($view->display_handler->options['css_class'], ['media-library-view--widget']);
      }
    }
  }

  /**
   * Add CSS class to view option.
   *
   * @param string $option
   *   The CSS class option.
   * @param array $classes
   *   The list of classes to add.
   */
  protected function addClassToOption(string &$option, array $classes): void {
    $existing_classes = \preg_split('/\s+/', $option);
    if (\is_array($existing_classes)) {
      $existing_classes = \array_filter($existing_classes);
      $existing_classes = \array_merge($existing_classes, $classes);
      $option = \implode(' ', \array_unique($existing_classes));
    }
  }

}
