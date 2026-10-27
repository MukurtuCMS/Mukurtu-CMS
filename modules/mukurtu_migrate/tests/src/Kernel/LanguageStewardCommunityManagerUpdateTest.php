<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\migrate\Row;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40208().
 *
 * The language community membership migration only mapped "administrator
 * member" to community manager, so Mukurtu 3 language stewards arrived as
 * plain community members.
 *
 * @see mukurtu_migrate_update_40208()
 */
#[Group('mukurtu_migrate')]
class LanguageStewardCommunityManagerUpdateTest extends ProtocolAwareEntityTestBase {

  /**
   * The membership migration's id.
   */
  const MIGRATION = 'mukurtu_cms_v3_language_community_community_memberships';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_drupal',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * A migrated language community.
   */
  protected Community $community;

  /**
   * The protocol migrated from the same v3 language community.
   */
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['community' => 'community_manager', 'protocol' => 'language_steward'] as $group_type => $name) {
      OgRole::create(['name' => $name, 'label' => $name])
        ->setGroupType($group_type)
        ->setGroupBundle($group_type)
        ->save();
    }

    $this->community = Community::create([
      'name' => 'Language community',
      'field_access_mode' => 'community-only',
    ]);
    $this->community->save();
    $this->protocol = Protocol::create([
      'name' => 'Language community',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $this->protocol->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';
  }

  /**
   * Maps one v3 language community node to a community and a protocol.
   *
   * The shipped migrations are installed and their id maps write the rows,
   * so the map tables have the names and columns a migrated site has.
   */
  protected function createMapTables(int $community_id, int $protocol_id): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $destinations = [
      'mukurtu_cms_v3_language_communities' => $community_id,
      'mukurtu_cms_v3_language_community_protocols' => $protocol_id,
    ];
    foreach ($destinations as $id => $destination_id) {
      $data = Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.' . $id . '.yml'));
      \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
      $row = new Row(['nid' => 70], ['nid' => ['type' => 'integer']]);
      \Drupal::service('plugin.manager.migration')->createInstance($id)->getIdMap()->saveIdMapping($row, [$destination_id]);
    }
  }

  /**
   * Creates a user with the given community and protocol roles.
   *
   * @param string[]|null $community_roles
   *   The community roles, or NULL for no community membership.
   * @param string[] $protocol_roles
   *   The protocol roles.
   */
  protected function createMember(?array $community_roles, array $protocol_roles): UserInterface {
    $user = $this->createUser();
    if ($community_roles !== NULL) {
      $this->community->addMember($user, $community_roles);
    }
    $this->protocol->addMember($user, $protocol_roles);
    return $user;
  }

  /**
   * Returns whether a user is a manager of the community.
   */
  protected function isManager(UserInterface $user): bool {
    $membership = Og::getMembership($this->community, $user, OgMembershipInterface::ALL_STATES);
    return $membership && $membership->hasRole('community-community-community_manager');
  }

  /**
   * Tests that only language stewards with a community membership change.
   */
  public function testStewardsBecomeCommunityManagers(): void {
    $this->createMapTables((int) $this->community->id(), (int) $this->protocol->id());
    $steward = $this->createMember([], ['language_steward']);
    $manager = $this->createMember(['community_manager'], ['language_steward']);
    $contributor = $this->createMember([], []);
    $removed = $this->createMember(NULL, ['language_steward']);

    $this->assertSame(
      'Gave the Community Manager role to 1 Mukurtu 3 language stewards in their migrated language communities, so they can manage the community and its protocol.',
      (string) mukurtu_migrate_update_40208()
    );

    \Drupal::entityTypeManager()->getStorage('og_membership')->resetCache();
    $this->assertTrue($this->isManager($steward));
    $this->assertTrue($this->isManager($manager));
    $this->assertFalse($this->isManager($contributor));
    $this->assertNull(Og::getMembership($this->community, $removed, OgMembershipInterface::ALL_STATES));

    $this->assertNull(mukurtu_migrate_update_40208());
  }

  /**
   * Tests that a community that wasn't migrated is left alone.
   */
  public function testUnmigratedCommunityIsSkipped(): void {
    $this->createMapTables(9999, 9999);
    $steward = $this->createMember([], ['language_steward']);

    $this->assertNull(mukurtu_migrate_update_40208());
    $this->assertFalse($this->isManager($steward));
  }

  /**
   * Tests that a broken migration elsewhere on the site doesn't stop repair.
   *
   * Loading any migration discovers them all, and a migration whose source
   * plugin is gone makes that throw.
   */
  public function testBrokenMigrationDoesNotStopRepair(): void {
    $this->createMapTables((int) $this->community->id(), (int) $this->protocol->id());
    \Drupal::entityTypeManager()->getStorage('migration')->create([
      'id' => 'broken_example',
      'label' => 'Broken example',
      'migration_tags' => [],
      'migration_dependencies' => [],
      'source' => ['plugin' => 'removed_module_source'],
      'process' => [],
      'destination' => ['plugin' => 'entity:user'],
    ])->save();
    $steward = $this->createMember([], ['language_steward']);

    $this->assertNotNull(mukurtu_migrate_update_40208());
    \Drupal::entityTypeManager()->getStorage('og_membership')->resetCache();
    $this->assertTrue($this->isManager($steward));
  }

  /**
   * Tests that a site without the migrate map tables is skipped.
   */
  public function testMissingMapTablesAreSkipped(): void {
    $steward = $this->createMember([], ['language_steward']);

    $this->assertNull(mukurtu_migrate_update_40208());
    $this->assertFalse($this->isManager($steward));
  }

  /**
   * Tests that the shipped mapping is replaced and a custom one is kept.
   */
  public function testMigrationConfig(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $data = Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.' . self::MIGRATION . '.yml'));
    $shipped = $data['process']['roles'];
    $original = $shipped;
    unset($original[0]['map']['language steward'], $original[1]);
    $data['process']['roles'] = $original;
    \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
    $config = \Drupal::configFactory()->getEditable('migrate_plus.migration.' . self::MIGRATION);

    mukurtu_migrate_update_40208();
    $this->assertSame($shipped, \Drupal::config('migrate_plus.migration.' . self::MIGRATION)->get('process.roles'));

    $custom = $original;
    $custom[0]['default_value'] = 'community-community-community_manager';
    $config->set('process.roles', $custom)->save();
    mukurtu_migrate_update_40208();
    $this->assertSame($custom, \Drupal::config('migrate_plus.migration.' . self::MIGRATION)->get('process.roles'));

    \Drupal::configFactory()->getEditable('migrate_plus.migration.' . self::MIGRATION)->delete();
    mukurtu_migrate_update_40208();
    $this->assertTrue(\Drupal::config('migrate_plus.migration.' . self::MIGRATION)->isNew());
  }

}
