<?php

namespace Drupal\leaflet_views\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the leaflet_views_ajax_popup module.
 */
class LeafletAjaxPopupController extends ControllerBase {

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * Constructs a new LeafletAjaxPopupController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, RendererInterface $renderer) {
    $this->entityManager = $entity_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * User LeafletAjaxPopup page access checker.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check the permission for view.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The Access check results.
   */
  public function accessCheck(EntityInterface $entity) {
    return AccessResult::allowedIf($entity->access('view'));
  }

  /**
   * Leaflet Ajax Popup build callback..
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose build to return.
   * @param string $view_mode
   *   The view mode identifier.
   * @param string $langcode
   *   The langcode to render the entity by.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response to return.
   */
  public function popupBuild(EntityInterface $entity, $view_mode, $langcode = NULL) {
    $entity_view_builder = $this->entityManager->getViewBuilder($entity->getEntityTypeId());
    $build = $entity_view_builder->view($entity, $view_mode, $langcode);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($this->getPopupIdentifierSelector($entity->getEntityTypeId(), $entity->id(), $view_mode, $langcode), $build));
    return $response;
  }

  /**
   * Get popup identifier.
   *
   * @param string $entityType
   *   The entity type.
   * @param int $entityId
   *   The entity id.
   * @param string $viewMode
   *   The view mode.
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The identifier.
   */
  public static function getPopupIdentifier($entityType, $entityId, $viewMode, $langcode) {
    return "$entityType-$entityId-$viewMode-$langcode";
  }

  /**
   * Get popup identifier attribute.
   *
   * @param string $entityType
   *   The entity type.
   * @param int $entityId
   *   The entity id.
   * @param string $viewMode
   *   The view mode.
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The identifier selector.
   */
  public static function getPopupIdentifierAttribute($entityType, $entityId, $viewMode, $langcode) {
    return sprintf('data-leaflet-popup-ajax-entity="%s"', self::getPopupIdentifier($entityType, $entityId, $viewMode, $langcode));
  }

  /**
   * Get popup identifier selector.
   *
   * @param string $entityType
   *   The entity type.
   * @param int $entityId
   *   The entity id.
   * @param string $viewMode
   *   The view mode.
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The identifier selector.
   */
  public static function getPopupIdentifierSelector($entityType, $entityId, $viewMode, $langcode) {
    return sprintf('[%s]', self::getPopupIdentifierAttribute($entityType, $entityId, $viewMode, $langcode));
  }

}
