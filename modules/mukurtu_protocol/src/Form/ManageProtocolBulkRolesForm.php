<?php

namespace Drupal\mukurtu_protocol\Form;

/**
 * Per-user role management form for the protocol members bulk action.
 */
class ManageProtocolBulkRolesForm extends ManageBulkRolesFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_manage_protocol_bulk_roles_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTempStoreCollection(): string {
    return 'mukurtu_protocol.manage_protocol_roles';
  }

  /**
   * {@inheritdoc}
   */
  protected function getMembersRouteId(): string {
    return 'mukurtu_protocol.protocol_members_list';
  }

}
