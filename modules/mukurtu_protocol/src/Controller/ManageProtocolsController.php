<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;

/**
 * Controller for protocol management pages.
 */
class ManageProtocolsController extends ControllerBase {

  /**
   * Redirect to the protocol's members list.
   */
  public function manageProtocolRedirect(ProtocolInterface $protocol) {
    return $this->redirect('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()], ['parameters' => ['group' => ['type' => 'entity:protocol']]]);
  }

}
