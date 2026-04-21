<?php

namespace Drupal\gin;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

include_once __DIR__ . '/../gin.theme';
_gin_include_theme_includes();

/**
 * Service to handle overridden user settings.
 */
class GinNavigation implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Settings constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumbBuilder
   *   The breadcrumb builder.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTree
   *   The menu link tree.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected BreadcrumbBuilderInterface $breadcrumbBuilder,
    protected RouteMatchInterface $routeMatch,
    protected MenuLinkTreeInterface $menuLinkTree,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('breadcrumb'),
      $container->get('current_route_match'),
      $container->get('menu.link_tree'),
      $container->get('module_handler'),
    );
  }

  /**
   * Get Navigation Admin Menu Items.
   */
  public function getNavigationAdminMenuItems(): array {
    $parameters = new MenuTreeParameters();
    $parameters->setMinDepth(2)->setMaxDepth(4)->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load('admin', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    if ($this->moduleHandler->moduleExists('toolbar')) {
      $manipulators[] = ['callable' => 'toolbar_menu_navigation_links'];
    }
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $build = $this->menuLinkTree->build($tree);
    /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
    $first_link = reset($tree)->link;
    // Get the menu name of the first link.
    $menu_name = $first_link->getMenuName();
    $build['#menu_name'] = $menu_name;
    $build['#theme'] = 'menu_region__middle';

    // Loop through menu items and add the plugin id as a class.
    foreach ($tree as $item) {
      if ($item->access->isAllowed()) {
        $plugin_id = $item->link->getPluginId();
        $plugin_class = str_replace('.', '_', $plugin_id);
        $build['#items'][$plugin_id]['class'] = $plugin_class;
      }
    }

    // Remove content and help from admin menu.
    unset($build['#items']['system.admin_content']);
    unset($build['#items']['help.main']);
    $build['#title'] = $this->t('Administration');

    return $build;
  }

  /**
   * Get Navigation Bookmarks.
   */
  public function getNavigationBookmarksMenuItems(): array {
    // Check if the shortcut module is installed.
    // phpcs:disable
    // @phpstan-ignore-next-line
    if (\Drupal::hasService('shortcut.lazy_builders') === TRUE) {
      // @phpstan-ignore-next-line
      $shortcuts = \Drupal::service('shortcut.lazy_builders')->lazyLinks()['shortcuts'];
      // phpcs:enable
      $shortcuts['#theme'] = 'menu_region__top';
      $shortcuts['#menu_name'] = 'bookmarks';
      $shortcuts['#title'] = $this->t('Bookmarks');
      return $shortcuts;
    }
    else {
      return [];
    }
  }

  /**
   * Get Navigation Create menu.
   */
  public function getNavigationCreateMenuItems(): array {

    // Needs to be this syntax to
    // support older PHP versions
    // for Drupal 9.0+.
    $create_type_items = [];
    $create_item_url = '';

    // Get node types.
    if ($this->entityTypeManager->hasDefinition('node')) {
      $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      $content_type_items = [];

      foreach ($content_types as $item) {
        if ($this->hasLinkAccessPermission('node.add', ['node_type' => $item->id()])) {
          $content_type_items[] = [
            'title' => $item->label(),
            'class' => $item->id(),
            'url' => Url::fromRoute('node.add', ['node_type' => $item->id()]),
          ];
        }
      }

      $create_type_items = array_merge($content_type_items);
    }

    // Get block types.
    if ($this->entityTypeManager->hasDefinition('block_content')) {
      $block_content_types = $this->entityTypeManager
        ->getStorage('block_content_type')
        ->loadMultiple();
      $block_type_items = [];

      foreach ($block_content_types as $item) {
        if ($this->hasLinkAccessPermission('block_content.add_form', ['block_content_type' => $item->id()])) {
          $block_type_items[] = [
            'title' => $item->label(),
            'class' => $item->id(),
            'url' => Url::fromRoute('block_content.add_form', ['block_content_type' => $item->id()]),
          ];
        }
      }

      if ($block_type_items) {
        $create_type_items = array_merge(
          $create_type_items,
          [
            [
              'title' => $this->t('Blocks'),
              'class' => 'blocks',
              'url' => '',
              'below' => $block_type_items,
            ],
          ]
        );
      }
    }

    // Get media types.
    if ($this->entityTypeManager->hasDefinition('media')) {
      $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
      $media_type_items = [];

      foreach ($media_types as $item) {
        if ($this->hasLinkAccessPermission('entity.media.add_form', ['media_type' => $item->id()])) {
          $media_type_items[] = [
            'title' => $item->label(),
            'class' => $item->label(),
            'url' => Url::fromRoute('entity.media.add_form', ['media_type' => $item->id()]),
          ];
        }
      }

      if ($media_type_items) {
        $create_type_items = array_merge(
          $create_type_items,
          [
            [
              'title' => $this->t('Media'),
              'class' => 'media',
              'url' => '',
              'below' => $media_type_items,
            ],
          ]
        );
      }
    }

    // Get taxonomy types.
    if ($this->entityTypeManager->hasDefinition('taxonomy_term')) {
      $taxonomy_types = $this->entityTypeManager
        ->getStorage('taxonomy_vocabulary')
        ->loadMultiple();
      $taxonomy_type_items = [];

      foreach ($taxonomy_types as $item) {
        if ($this->hasLinkAccessPermission('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $item->id()])) {
          $taxonomy_type_items[] = [
            'title' => $item->label(),
            'class' => $item->id(),
            'url' => Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $item->id()]),
          ];
        }
      }

      if ($taxonomy_type_items) {
        $create_type_items = array_merge(
          $create_type_items,
          [
            [
              'title' => $this->t('Taxonomy'),
              'class' => 'taxonomy',
              'url' => '',
              'below' => $taxonomy_type_items,
            ],
          ]
        );
      }
    }

    if (!$create_type_items && !$create_item_url) {
      return [];
    }

    // Generate menu items.
    $create_items['create'] = [
      'title' => $this->t('Create'),
      'class' => 'create',
      'url' => $create_item_url,
      'below' => $create_type_items,
    ];

    return [
      '#theme' => 'menu_region__middle',
      '#items' => $create_items,
      '#menu_name' => 'create',
      '#title' => $this->t('Create Navigation'),
    ];
  }

  /**
   * Get Navigation Content menu.
   */
  public function getNavigationContentMenuItems(): array {
    $create_content_items = [];

    // Get Content menu item.
    if ($this->entityTypeManager->hasDefinition('node')) {
      if ($this->hasLinkAccessPermission('system.admin_content')) {
        $create_content_items['content'] = [
          'title' => $this->t('Content'),
          'class' => 'content',
          'url' => Url::fromRoute('system.admin_content')->toString(),
        ];
      }
    }

    // Get Blocks menu item.
    if ($this->entityTypeManager->hasDefinition('block_content')) {
      if ($this->hasLinkAccessPermission('entity.block_content.collection')) {
        $create_content_items['blocks'] = [
          'title' => $this->t('Blocks'),
          'class' => 'blocks',
          'url' => Url::fromRoute('entity.block_content.collection')->toString(),
        ];
      }
    }

    // Get File menu item.
    if ($this->entityTypeManager->hasDefinition('file')) {
      if ($this->hasLinkAccessPermission('view.files.page_1')) {
        $create_content_items['files'] = [
          'title' => $this->t('Files'),
          'class' => 'files',
          'url' => Url::fromRoute('view.files.page_1')->toString(),
        ];
      }
    }

    // Get Media menu item.
    if ($this->entityTypeManager->hasDefinition('media')) {
      if ($this->hasLinkAccessPermission('view.media.media_page_list')) {
        $create_content_items['media'] = [
          'title' => $this->t('Media'),
          'class' => 'media',
          'url' => Url::fromRoute('view.media.media_page_list')->toString(),
        ];
      }
    }

    return [
      '#theme' => 'menu_region__middle',
      '#items' => $create_content_items,
      '#menu_name' => 'content',
      '#title' => $this->t('Content Navigation'),
    ];
  }

  /**
   * Get Navigation User menu.
   */
  public function getMenuNavigationUserItems(): array {
    $user_items = [
      [
        'title' => $this->t('Profile'),
        'class' => 'profile',
        'url' => Url::fromRoute('user.page')->toString(),
      ],
      [
        'title' => $this->t('Settings'),
        'class' => 'settings',
        'url' => Url::fromRoute('entity.user.admin_form')->toString(),
      ],
      [
        'title' => $this->t('Log out'),
        'class' => 'logout',
        'url' => Url::fromRoute('user.logout')->toString(),
      ],
    ];
    return [
      '#theme' => 'menu_region__bottom',
      '#items' => $user_items,
      '#menu_name' => 'user',
      '#title' => $this->t('User'),
    ];
  }

  /**
   * Get Navigation.
   */
  public function getNavigationStructure() {
    // Get navigation items.
    $menu['top']['create'] = $this->getNavigationCreateMenuItems();
    $menu['middle']['content'] = $this->getNavigationContentMenuItems();
    $menu['middle']['admin'] = $this->getNavigationAdminMenuItems();
    $menu['bottom']['user'] = $this->getMenuNavigationUserItems();

    return [
      '#theme' => 'navigation',
      '#menu_top' => $menu['top'],
      '#menu_middle' => $menu['middle'],
      '#menu_bottom' => $menu['bottom'],
      '#attached' => [
        'library' => [
          'gin/navigation',
        ],
      ],
      '#access' => $this->currentUser->hasPermission('access toolbar') || $this->currentUser->hasPermission('access navigation'),
    ];
  }

  /**
   * Get Active trail.
   */
  public function getNavigationActiveTrail() {
    // Get the breadcrumb paths to maintain active trail in the toolbar.
    $links = $this->breadcrumbBuilder->build($this->routeMatch)->getLinks();
    $paths = [];
    foreach ($links as $link) {
      $paths[] = $link->getUrl()->getInternalPath();
    }

    return $paths;
  }

  /**
   * Check the current user's access permission for provided links.
   *
   * @param string $route_name
   *   The name of the route.
   * @param array $route_parameters
   *   (optional) The route parameter value.
   *
   * @return bool
   *   Return true if the user has the access to link or false if not.
   */
  public function hasLinkAccessPermission($route_name, ?array $route_parameters = []) {
    $url = Url::fromRoute($route_name, $route_parameters);
    $has_access = $url->access($this->currentUser);
    return $has_access;
  }

}
