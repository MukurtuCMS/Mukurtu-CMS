<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;

use Drupal\Core\Link;
use Drupal\Core\Url;

class MukurtuProtocolController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName()
  {
    return 'mukurtu_protocol';
  }

  public function addUserAsProtocolMember($nid, $user) {
/*     return [
      '#markup' => "This is nid = $nid, user = $user",
    ]; */

    $response = new AjaxResponse();
    $response->addCommand(new AlertCommand("This is nid = $nid, user = $user"));
    return $response;
  }

}
