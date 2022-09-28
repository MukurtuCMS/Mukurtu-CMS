<?php

namespace Drupal\mukurtu_multipage_items\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\mukurtu_multipage_items\MultipageItemInterface;

/**
 * Returns responses for Mukurtu Multipage Items routes.
 */
class MultipageItemViewController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  protected function getMultipageEntity($node) {
    return $this->entityTypeManager()->getStorage('multipage_item')->load(1);
  }

  public function getSelectedPageAjax(MultipageItemInterface $multipageitem, NodeInterface $page) {
    $response = new AjaxResponse();
    if ($multipageitem->hasPage($page) && $page->access('view')) {
      $view_builder = $this->entityTypeManager()->getViewBuilder('node');
      $content['mukurtu_multipage_item_selected_page'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'mukurtu-multipage-item-selected-page',
        ],
      ];

      $content['mukurtu_multipage_item_selected_page']['teaser'] = $view_builder->view($page, 'full');
      $response->addCommand(new ReplaceCommand('#mukurtu-multipage-item-selected-page', $content));
    }
    return $response;
  }

  /**
   * Builds the response.
   */
  public function build($mpi, $page = NULL) {
    $view_builder = $this->entityTypeManager()->getViewBuilder('node');

    /** @var \Drupal\mukurtu_multipage_items\Entity\MultipageItem $mpi */
    $current_page = $page ?? $mpi->getFirstPage();
    $pages = $mpi->getPages(TRUE);
    $toc_options = array_map(fn($p) => $p->getTitle(), $pages);
    $toc = [
      '#id' => 'multipage-item-table-of-contents',
      '#type' => 'select',
      '#title' => $this->t('Jump to page'),
      '#options' => $toc_options,
      '#value' => $current_page->id(),
    ];

    return [
      '#theme' => 'multipage_item_book_view',
      '#pages' => array_map(fn($p) => $view_builder->view($p, 'content_browser'), $pages),
      '#page_nav_attributes' => NULL,
      '#table_of_contents' => $toc,
      '#current_page_attributes' => ['id' => 'current-page'],
      '#current_page' => $view_builder->view($current_page, 'full'),
      '#attached' => [
        'library' => [
          'mukurtu_multipage_items/multipage-view-nav',
          'mukurtu_multipage_items/splide',
        ],
      ],
    ];
  }

}
