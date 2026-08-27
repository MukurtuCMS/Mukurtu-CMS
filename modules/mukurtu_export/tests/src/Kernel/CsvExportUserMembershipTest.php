<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\og\Entity\OgRole;

/**
 * Tests exporting user community/protocol memberships.
 */
class CsvExportUserMembershipTest extends CsvExportFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The community_manager/community_member/community_affiliate OG roles
    // aren't installed by this test base; create them directly (mirroring
    // ManageBulkRolesFormTest's pattern), then give the base class's
    // roleless community membership (CsvExportFieldTestBase::setUp() calls
    // addMember() with no roles) an explicit role to export.
    foreach (['community_manager', 'community_affiliate', 'community_member'] as $role_name) {
      $role = OgRole::create(['name' => $role_name, 'label' => $role_name, 'permissions' => []]);
      $role->setGroupType('community');
      $role->setGroupBundle('community');
      $role->save();
    }
    $this->community->setRoles($this->currentUser, ['community_manager']);
  }

  /**
   * Test exporting a user's community membership and role.
   */
  public function testCommunityMembershipExport() {
    $event = new EntityFieldExportEvent('csv', $this->currentUser, 'communities', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals([$this->community->label() . '>community_manager'], $event->getValue());
  }

  /**
   * Test exporting a user's protocol membership and role.
   */
  public function testProtocolMembershipExport() {
    $event = new EntityFieldExportEvent('csv', $this->currentUser, 'protocols', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals([$this->protocol->label() . '>protocol_steward'], $event->getValue());
  }

  /**
   * Test that a colon in a community's name is preserved on export, since
   * '>' -- not ':' -- is the compound-value delimiter.
   */
  public function testCommunityNameWithColonIsPreservedOnExport() {
    $community = Community::create(['name' => 'Smith: A Family History']);
    $community->save();
    $community->addMember($this->currentUser, ['community_manager']);
    $community->setRoles($this->currentUser, ['community_manager']);

    $event = new EntityFieldExportEvent('csv', $this->currentUser, 'communities', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertContains('Smith: A Family History>community_manager', $event->getValue());
  }

  /**
   * Test exporting a user with no community memberships.
   */
  public function testNoMembershipExportsEmpty() {
    $user = $this->createUser();
    $user->save();
    $event = new EntityFieldExportEvent('csv', $user, 'communities', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals([], $event->getValue());
  }

  /**
   * Test exporting multiple community memberships with multiple roles.
   */
  public function testMultipleMembershipsAndRolesExport() {
    $second_community = Community::create(['name' => 'Second Community']);
    $second_community->save();
    // addMember()'s $roles argument is silently ignored if a membership
    // already exists (e.g. the auto-created, roleless owner membership OG
    // grants the community's creator on save) -- explicitly setRoles()
    // afterward, matching the established pattern elsewhere (e.g.
    // CommunityAddForm::save(), ProtocolAwareUserContent::applyGroupMembershipUpdates()).
    $second_community->addMember($this->currentUser, ['community_affiliate']);
    $second_community->setRoles($this->currentUser, ['community_affiliate']);

    $event = new EntityFieldExportEvent('csv', $this->currentUser, 'communities', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEqualsCanonicalizing(
      [$this->community->label() . '>community_manager', 'Second Community>community_affiliate'],
      $event->getValue(),
    );
  }

  /**
   * Test that pass/access/login/init are never exportable fields for users.
   */
  public function testSensitiveFieldsExcludedFromMapping() {
    $mapped = $this->export_config->getMappedFields('user', 'user');
    $field_names = array_column($mapped, 'field_name');
    foreach (['pass', 'access', 'login', 'init'] as $sensitive_field) {
      $this->assertNotContains($sensitive_field, $field_names);
    }
  }

  /**
   * Test that the virtual communities/protocols/account_status columns are
   * offered when no user__user mapping exists yet.
   */
  public function testMembershipColumnsInMapping() {
    $mapped = $this->export_config->getMappedFields('user', 'user');
    $field_names = array_column($mapped, 'field_name');
    $this->assertContains('communities', $field_names);
    $this->assertContains('protocols', $field_names);
    $this->assertContains('account_status', $field_names);
  }

  /**
   * Test that a preset already migrated to the unified account_status
   * mapping (see mukurtu_export_update_40025()) is reflected correctly in
   * the settings form: the account_status row is shown as mapped/exported,
   * and the raw status/field_pending fields it supersedes never reappear
   * as unmapped rows.
   */
  public function testMigratedAccountStatusMappingIsReflectedInForm() {
    $this->export_config->set('entity_fields_export_list', [
      'user__user' => ['account_status' => 'Account Status'],
    ])->save();

    $mapped = $this->export_config->getMappedFields('user', 'user');
    $field_names = array_column($mapped, 'field_name');
    $this->assertNotContains('status', $field_names);
    $this->assertNotContains('field_pending', $field_names);

    $account_status_rows = array_filter($mapped, fn($row) => $row['field_name'] === 'account_status');
    $this->assertCount(1, $account_status_rows);
    $account_status_row = reset($account_status_rows);
    $this->assertTrue($account_status_row['export']);
    $this->assertEquals('Account Status', $account_status_row['csv_header_label']);
  }

}
