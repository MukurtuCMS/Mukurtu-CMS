<?php

namespace Drupal\mukurtu_community\Controller;

use Drupal\Core\Controller\ControllerBase;

class MukurtuCommunityManageController extends ControllerBase {
  /**
   * Display the manage communities page.
   */
  public function content() {
    $build = [];
    $build[] = $this->displayCommunities();
    return $build;
  }

  protected function displayCommunities($parent = NULL, $view_mode = 'content_browser') {
    $build = [];

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $op = is_null($parent) ? 'IS NULL' : '=';
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'community')
      ->condition('field_parent_community', $parent, $op);
    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      return $build;
    }

    $build[] = ['#markup' => '<ul>'];
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($entity_ids);
    foreach ($nodes as $node) {
      $nodeBuild = [];

      // Render the current community.
      $nodeBuild[] = ['#markup' => '<li>'];
      $nodeBuild[] = $view_builder->view($node, $view_mode);
      $nodeBuild[] = $this->displayCommunityProtocols($node);

      // Get any child communities.
      $nodeBuild[] = $this->displayCommunities($node->id());
      $nodeBuild[] = ['#markup' => '</li>'];

      $build[] = $nodeBuild;
    }
    $build[] = ['#markup' => '</ul>'];
    return $build;
  }

  protected function displayCommunityProtocols($community) {
    $build = [];
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'protocol')
      ->condition(MUKURTU_COMMUNITY_FIELD_NAME_COMMUNITY, $community->id(), '=');
    $entity_ids = $query->execute();
    if (empty($entity_ids)) {
      return $build;
    }

    $build[] = ['#markup' => '<h3>' . $this->t('Protocols') . '</h3>'];
    $build[] = ['#markup' => '<ul>'];
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($entity_ids);
    foreach ($nodes as $node) {
      $nodeBuild = [];
      $nodeBuild[] = ['#markup' => '<li>'];
      $link_object = \Drupal\Core\Link::createFromRoute($node->getTitle(), 'entity.node.canonical', ['node' => $node->id()]);
      $nodeBuild[] = $link_object->toRenderable();
      $nodeBuild[] = ['#markup' => '</li>'];

      $build[] = $nodeBuild;
    }
    $build[] = ['#markup' => '</ul>'];

    return $build;
  }
}
