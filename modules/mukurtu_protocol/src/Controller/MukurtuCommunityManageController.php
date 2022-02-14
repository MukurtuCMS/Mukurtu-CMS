<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

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

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('community');
    $op = is_null($parent) ? 'IS NULL' : '=';
    $query = \Drupal::entityQuery('community')
      //->condition('type', 'community')
      ->condition('field_parent_community', $parent, $op)
      ->sort('name');
    $entity_ids = $query->execute();
/*     dpm($entity_ids);
    return $build; */

    if (empty($entity_ids)) {
      return $build;
    }

    $build[] = ['#markup' => '<ul>'];
    $communities = \Drupal::entityTypeManager()->getStorage('community')->loadMultiple($entity_ids);
    foreach ($communities as $community) {
      $nodeBuild = [];

      // Render the current community.
      $nodeBuild[] = ['#markup' => '<li>'];
      $nodeBuild[] = $view_builder->view($community, $view_mode);
      //$nodeBuild[] = $this->displayCommunityProtocols($node);

      // Get any child communities.
      //$nodeBuild[] = $this->displayCommunities($community->id());
      $nodeBuild[] = ['#markup' => '</li>'];

      $build[] = $nodeBuild;
    }
    $build[] = ['#markup' => '</ul>'];
    return $build;
  }

  /* protected function displayCommunityProtocols($community) {
    $build = [];
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'protocol')
      ->condition(mukurtu_protocol_FIELD_NAME_COMMUNITY, $community->id(), '=')
      ->sort('title');
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
      $link_object = Link::createFromRoute($node->getTitle(), 'entity.node.canonical', ['node' => $node->id()]);
      $nodeBuild[] = $link_object->toRenderable();
      $nodeBuild[] = ['#markup' => '</li>'];

      $build[] = $nodeBuild;
    }
    $build[] = ['#markup' => '</ul>'];

    return $build;
  } */
}
