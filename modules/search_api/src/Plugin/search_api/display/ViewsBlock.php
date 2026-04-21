<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\search_api\Attribute\SearchApiViewsDisplay;
use Drupal\search_api\Event\IsRenderedInCurrentRequestEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Represents a Views block display.
 */
#[SearchApiViewsDisplay(
  id: 'views_block',
  deriver: ViewsDisplayDeriver::class,
  views_display_type: 'block'
)]
class ViewsBlock extends ViewsDisplayBase {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|null
   */
  protected $themeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|null
   */
  protected $moduleHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
   */
  protected $eventDispatcher;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->setThemeManager($container->get('theme.manager'));
    $plugin->setModuleHandler($container->get('module_handler'));
    $plugin->setEventDispatcher($container->get('event_dispatcher'));
    return $plugin;
  }

  /**
   * Retrieves the theme manager.
   *
   * @return \Drupal\Core\Theme\ThemeManagerInterface
   *   The theme manager.
   */
  public function getThemeManager(): ThemeManagerInterface {
    return $this->themeManager ?: \Drupal::service('theme.manager');
  }

  /**
   * Sets the theme manager.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The new theme manager.
   *
   * @return $this
   */
  public function setThemeManager(ThemeManagerInterface $theme_manager): self {
    $this->themeManager = $theme_manager;
    return $this;
  }

  /**
   * Retrieves the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function getModuleHandler(): ModuleHandlerInterface {
    return $this->moduleHandler ?: \Drupal::moduleHandler();
  }

  /**
   * Sets the module handler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The new module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler): self {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * Retrieves the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  public function getEventDispatcher(): EventDispatcherInterface {
    return $this->eventDispatcher ?: \Drupal::service('event_dispatcher');
  }

  /**
   * Sets the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The new event dispatcher.
   *
   * @return $this
   */
  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher): self {
    $this->eventDispatcher = $event_dispatcher;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest(): bool {
    // There can be more than one block rendering the display. If any block is
    // rendered, we return TRUE.
    $plugin_id = 'views_block:' . $this->pluginDefinition['view_id'] . '-' . $this->pluginDefinition['view_display'];
    $blocks = $this->getEntityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'plugin' => $plugin_id,
        'theme' => $this->getThemeManager()->getActiveTheme()->getName(),
      ]);
    /** @var \Drupal\block\BlockInterface $block */
    foreach ($blocks as $block) {
      if ($block->access('view')) {
        return TRUE;
      }
    }

    // Detect if the block is placed through Layout Builder in the current page.
    $is_rendered_in_current_request = FALSE;
    if ($this->getModuleHandler()->moduleExists('layout_builder')) {
      $params = $this->getCurrentRouteMatch()->getParameters();
      foreach ($params->getIterator() as $param) {
        if ($param instanceof ContentEntityInterface
            && $param->hasField(OverridesSectionStorage::FIELD_NAME)
            && !$param->get(OverridesSectionStorage::FIELD_NAME)->isEmpty()) {
          $layout_builder_sections = clone $param->get(OverridesSectionStorage::FIELD_NAME);
          $layout_builder_sections->filter(
            function (LayoutSectionItem $section) use ($plugin_id) {
              return (bool) array_filter(
                $section->section->getComponents(),
                function (SectionComponent $component) use ($plugin_id) {
                  return $component->getPluginId() === $plugin_id;
                }
              );
            }
          );

          // The block section has been detected if the filtered layout builder
          // sections field is not empty.
          if (!$layout_builder_sections->isEmpty()) {
            $is_rendered_in_current_request = TRUE;
            break;
          }
        }
      }
    }

    // Allow other modules to alter the detection result.
    $event = new IsRenderedInCurrentRequestEvent($this, $is_rendered_in_current_request);
    $this->getEventDispatcher()->dispatch($event, SearchApiEvents::IS_RENDERED_IN_CURRENT_REQUEST);

    return $event->isRendered();
  }

}
