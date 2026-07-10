<?php

namespace Drupal\mukurtu_protocol\Form;

/**
 * Per-user role management form for the community members bulk action.
 */
class ManageCommunityBulkRolesForm extends ManageBulkRolesFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_manage_community_bulk_roles_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTempStoreCollection(): string {
    return 'mukurtu_protocol.manage_community_roles';
  }

  /**
   * {@inheritdoc}
   */
  protected function getMembersRouteId(): string {
    return 'mukurtu_protocol.community_members_list';
  }

}
