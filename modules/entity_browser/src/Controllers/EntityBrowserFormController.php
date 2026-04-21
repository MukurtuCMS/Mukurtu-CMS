<?php

namespace Drupal\entity_browser\Controllers;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\HtmlFormController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Standalone entity browser page.
 */
class EntityBrowserFormController extends HtmlFormController implements ContainerInjectionInterface {

  /**
   * Current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The browser storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $browserStorage;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs Entity browser form controller.
   *
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
   *   The argument resolver.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Current route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   */
  public function __construct(ArgumentResolverInterface $argument_resolver, FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, Request $request) {
    parent::__construct($argument_resolver, $form_builder, $class_resolver);
    $this->currentRouteMatch = $route_match;
    $this->browserStorage = $entity_type_manager->getStorage('entity_browser');
    $this->request = $request;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel.controller.argument_resolver'),
      $container->get('form_builder'),
      $container->get('class_resolver'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormObject(RouteMatchInterface $route_match, $form_arg) {
    $browser = $this->loadBrowser();
    if ($original_path = $this->request->get('original_path')) {
      $browser->addAdditionalWidgetParameters(['path_parts' => explode('/', $original_path)]);
    }

    return $browser->getFormObject();
  }

  /**
   * Standalone entity browser title callback.
   */
  public function title() {
    $browser = $this->loadBrowser();
    return Xss::filter($browser->label());
  }

  /**
   * Loads entity browser object for this page.
   *
   * @return \Drupal\entity_browser\EntityBrowserInterface
   *   Loads the entity browser object
   */
  protected function loadBrowser() {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $this->currentRouteMatch->getRouteObject();
    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    return $this->browserStorage->load($route->getDefault('entity_browser_id'));
  }

}
