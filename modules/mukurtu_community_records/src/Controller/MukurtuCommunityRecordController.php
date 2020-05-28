<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;

use Drupal\Core\Link;
use Drupal\Core\Url;

class MukurtuCommunityRecordController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'mukurtu_community_records';
  }

  public function addNewCommunityRecord($node) {
    $response = new AjaxResponse();
    $response->addCommand(new AlertCommand("Add new community record for node = $node"));
    return $response;
  }

}
