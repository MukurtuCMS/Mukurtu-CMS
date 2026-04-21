<?php

namespace Drupal\layout_builder_restrictions\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Override the controller for layout_builder.move_block.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('layout_builder.move_block')) {
      $defaults = $route->getDefaults();
      $defaults['_controller'] = '\Drupal\layout_builder_restrictions\Controller\MoveBlockController::build';
      $route->setDefaults($defaults);
    }
    if ($route = $collection->get('layout_builder.move_block_form')) {
      // Provide validation to the Layout Builder MoveBlock form.
      $route->setDefault('_form', '\Drupal\layout_builder_restrictions\Form\MoveBlockForm');
    }
    // Add inline block filtering to parent class
    // Drupal\layout_builder\Controller\ChooseBlockController.
    if ($route = $collection->get('layout_builder.choose_block')) {
      $defaults = $route->getDefaults();
      $defaults['_controller'] = '\Drupal\layout_builder_restrictions\Controller\ChooseBlockController::build';
      $route->setDefaults($defaults);
    }
    if ($route = $collection->get('layout_builder.choose_inline_block')) {
      $defaults = $route->getDefaults();
      $defaults['_controller'] = '\Drupal\layout_builder_restrictions\Controller\ChooseBlockController::inlineBlockList';
      $route->setDefaults($defaults);
    }
  }

}
