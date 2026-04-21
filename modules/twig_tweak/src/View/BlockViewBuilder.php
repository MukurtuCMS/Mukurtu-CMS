<?php

namespace Drupal\twig_tweak\View;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Block view builder.
 */
class BlockViewBuilder {

  /**
   * The plugin.manager.block service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $pluginManagerBlock;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * Constructs a BlockViewBuilder object.
   */
  public function __construct(
    BlockManagerInterface $plugin_manager_block,
    ContextRepositoryInterface $context_repository,
    ContextHandlerInterface $context_handler,
    AccountInterface $account,
    RequestStack $request_stack,
    RouteMatchInterface $route_match,
    TitleResolverInterface $title_resolver,
  ) {
    $this->pluginManagerBlock = $plugin_manager_block;
    $this->contextRepository = $context_repository;
    $this->contextHandler = $context_handler;
    $this->account = $account;
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
    $this->titleResolver = $title_resolver;
  }

  /**
   * Builds the render array for a block.
   *
   * @param string $id
   *   The string of block plugin to render.
   * @param array $configuration
   *   (optional) Pass on any configuration to the plugin block.
   * @param bool $wrapper
   *   (optional) Whether or not use block template for rendering.
   *
   * @return array
   *   A renderable array representing the content of the block.
   */
  public function build(string $id, array $configuration = [], bool $wrapper = TRUE): array {

    $configuration += ['label_display' => BlockPluginInterface::BLOCK_LABEL_VISIBLE];

    /** @var \Drupal\Core\Block\BlockPluginInterface $block_plugin */
    $block_plugin = $this->pluginManagerBlock->createInstance($id, $configuration);

    // Inject runtime contexts.
    if ($block_plugin instanceof ContextAwarePluginInterface) {
      $contexts = $this->contextRepository->getRuntimeContexts($block_plugin->getContextMapping());
      $this->contextHandler->applyContextMapping($block_plugin, $contexts);
    }

    $build = [];
    $access = $block_plugin->access($this->account, TRUE);
    if ($access->isAllowed()) {
      // Title block needs a special treatment.
      if ($block_plugin instanceof TitleBlockPluginInterface) {
        // Account for the scenario that a NullRouteMatch is returned. This, for
        // example, is the case when Search API is indexing the site during
        // Drush cron.
        if ($route = $this->routeMatch->getRouteObject()) {
          $request = $this->requestStack->getCurrentRequest();
          $title = $this->titleResolver->getTitle($request, $route);
          $block_plugin->setTitle($title);
        }
      }

      // Place the content returned by the block plugin into a 'content' child
      // element, as a way to allow the plugin to have complete control of its
      // properties and rendering (for instance, its own #theme) without
      // conflicting with the properties used above.
      $build['content'] = $block_plugin->build();

      if ($block_plugin instanceof TitleBlockPluginInterface) {
        $build['content']['#cache']['contexts'][] = 'url';
      }
      // Some blocks return null instead of array when empty.
      // @see https://www.drupal.org/project/drupal/issues/3212354
      if ($wrapper && is_array($build['content']) && !Element::isEmpty($build['content'])) {
        $build += [
          '#theme' => 'block',
          '#id' => $configuration['id'] ?? NULL,
          '#attributes' => [],
          '#contextual_links' => [],
          '#configuration' => $block_plugin->getConfiguration(),
          '#plugin_id' => $block_plugin->getPluginId(),
          '#base_plugin_id' => $block_plugin->getBaseId(),
          '#derivative_plugin_id' => $block_plugin->getDerivativeId(),
        ];
        // Semantically, the content returned by the plugin is the block, and in
        // particular, #attributes and #contextual_links is information about
        // the *entire* block. Therefore, we must move these properties into the
        // top-level element.
        foreach (['#attributes', '#contextual_links'] as $property) {
          if (isset($build['content'][$property])) {
            $build[$property] = $build['content'][$property];
            unset($build['content'][$property]);
          }
        }
      }
    }

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->addCacheableDependency($block_plugin)
      ->applyTo($build);

    if (!isset($build['#cache']['keys'])) {
      $build['#cache']['keys'] = [
        'twig_tweak_block',
        $id,
        '[configuration]=' . hash('sha256', serialize($configuration)),
        '[wrapper]=' . (int) $wrapper,
      ];
    }

    return $build;
  }

}
