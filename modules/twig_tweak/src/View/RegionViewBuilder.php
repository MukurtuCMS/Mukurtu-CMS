<?php

namespace Drupal\twig_tweak\View;

use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Region view builder.
 */
class RegionViewBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * Constructs a RegionViewBuilder object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    TitleResolverInterface $title_resolver,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->titleResolver = $title_resolver;
  }

  /**
   * Builds the render array of a given region.
   *
   * @param string $region
   *   The region to build.
   * @param string $theme
   *   (optional) The name of the theme to load the region. If it is not
   *   provided then default theme will be used.
   *
   * @return array
   *   A render array to display the region content.
   */
  public function build(string $region, ?string $theme = NULL): array {

    $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
      'region' => $region,
      'theme'  => $theme ?: $this->configFactory->get('system.theme')->get('default'),
    ]);

    $view_builder = $this->entityTypeManager->getViewBuilder('block');

    $build = [];

    $entity_type = $this->entityTypeManager->getDefinition('block');
    $cache_metadata = (new CacheableMetadata())
      ->addCacheTags($entity_type->getListCacheTags())
      ->addCacheContexts($entity_type->getListCacheContexts());

    /** @var \Drupal\block\BlockInterface[] $blocks */
    foreach ($blocks as $id => $block) {
      $access = $block->access('view', NULL, TRUE);
      $cache_metadata = $cache_metadata->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $block_plugin = $block->getPlugin();
        if ($block_plugin instanceof TitleBlockPluginInterface) {
          $request = $this->requestStack->getCurrentRequest();
          if ($route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
            $block_plugin->setTitle($this->titleResolver->getTitle($request, $route));
          }
        }
        $build[$id] = $view_builder->view($block);
      }
    }

    if ($build) {
      $build['#region'] = $region;
      $build['#theme_wrappers'] = ['region'];
    }
    $cache_metadata->applyTo($build);

    return $build;
  }

}
