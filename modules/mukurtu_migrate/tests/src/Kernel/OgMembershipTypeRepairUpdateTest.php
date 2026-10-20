<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40206().
 *
 * The Mukurtu 3 membership migrations used the group's bundle as the
 * og_membership bundle, leaving memberships whose type doesn't exist and
 * whose edit form fatals.
 *
 * @see mukurtu_migrate_update_40206()
 */
#[Group('mukurtu_migrate')]
class OgMembershipTypeRepairUpdateTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The membership migrations and the bundle each one originally shipped.
   */
  const MIGRATIONS = [
    'mukurtu_cms_v3_community_memberships' => 'community',
    'mukurtu_cms_v3_language_community_community_memberships' => 'community',
    'mukurtu_cms_v3_protocol_memberships' => 'protocol',
    'mukurtu_cms_v3_language_community_protocol_memberships' => 'protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'entity_test',
    'og',
    'migrate',
    'migrate_plus',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('og_membership');
    $this->installConfig(['og']);

    Og::groupTypeManager()->addGroup('entity_test', 'entity_test');

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';
  }

  /**
   * Returns the shipped config for a migration.
   */
  protected function shippedMigration(string $id): array {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    return Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.' . $id . '.yml'));
  }

  /**
   * Creates a membership and forces its stored type.
   */
  protected function createMembershipWithType(string $type): OgMembershipInterface {
    $group = EntityTest::create(['name' => $this->randomString()]);
    $group->save();
    $membership = Og::createMembership($group, $this->createUser());
    $membership->save();

    \Drupal::database()->update('og_membership')
      ->fields(['type' => $type])
      ->condition('id', $membership->id())
      ->execute();
    \Drupal::entityTypeManager()->getStorage('og_membership')->resetCache();

    return OgMembership::load($membership->id());
  }

  /**
   * Tests that the shipped migrations create default-type memberships.
   */
  public function testShippedMigrationsUseDefaultType(): void {
    foreach (array_keys(static::MIGRATIONS) as $id) {
      $this->assertSame(OgMembershipInterface::TYPE_DEFAULT, $this->shippedMigration($id)['destination']['default_bundle'], $id);
    }
  }

  /**
   * Tests that memberships with a missing type are switched to the default.
   */
  public function testRepairsMembershipsWithMissingType(): void {
    $protocol = $this->createMembershipWithType('protocol');
    $community = $this->createMembershipWithType('community');
    $default = $this->createMembershipWithType(OgMembershipInterface::TYPE_DEFAULT);
    $this->assertNull($protocol->type->entity, 'Precondition: the membership type should not exist.');

    $message = mukurtu_migrate_update_40206();

    $this->assertNotNull($message);
    $this->assertStringContainsString('2', $message);
    foreach ([$protocol, $community, $default] as $membership) {
      $reloaded = OgMembership::load($membership->id());
      $this->assertSame(OgMembershipInterface::TYPE_DEFAULT, $reloaded->getType());
      $this->assertNotNull($reloaded->type->entity);
    }

    $this->assertNull(mukurtu_migrate_update_40206(), 'A second run should have nothing to fix.');
  }

  /**
   * Tests that shipped migration config is updated and custom config is not.
   */
  public function testUpdatesMigrationConfig(): void {
    $config_factory = \Drupal::configFactory();
    foreach (static::MIGRATIONS as $id => $bundle) {
      $data = $this->shippedMigration($id);
      $data['destination']['default_bundle'] = $bundle;
      \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
    }
    $config_factory->getEditable('migrate_plus.migration.mukurtu_cms_v3_protocol_memberships')
      ->set('destination.default_bundle', 'custom')
      ->save();

    mukurtu_migrate_update_40206();

    foreach (array_keys(static::MIGRATIONS) as $id) {
      $expected = $id === 'mukurtu_cms_v3_protocol_memberships' ? 'custom' : OgMembershipInterface::TYPE_DEFAULT;
      $this->assertSame($expected, $config_factory->get('migrate_plus.migration.' . $id)->get('destination.default_bundle'), $id);
    }
  }

}
