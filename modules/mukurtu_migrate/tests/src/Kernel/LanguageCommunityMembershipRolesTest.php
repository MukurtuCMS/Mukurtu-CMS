<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the language community membership migrations map v3 OG roles.
 *
 * In Mukurtu 3, "language steward" administers a language community, and
 * "administrator member" has no permissions. Stewards must arrive as
 * community managers, or they can't manage the community or its protocol.
 *
 * Only the 'roles' pipeline runs, against the shipped config, so the test
 * doesn't need the user and group migrations the lookups depend on.
 */
#[Group('mukurtu_migrate')]
class LanguageCommunityMembershipRolesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'migrate',
    'migrate_drupal',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * Runs the shipped 'roles' pipeline of a migration on v3 role names.
   *
   * @param string $id
   *   The migration id.
   * @param string[] $roles
   *   The v3 OG role names, as MukurtuOgMemberships::prepareRow() sets them.
   *
   * @return mixed
   *   The destination 'roles' value.
   */
  protected function mapRoles(string $id, array $roles): mixed {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $definition = Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.' . $id . '.yml'));
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);

    $row = new Row(['roles' => $roles]);
    (new MigrateExecutable($migration))->processRow($row, ['roles' => $migration->getProcess()['roles']]);
    return $row->getDestinationProperty('roles');
  }

  /**
   * Provides v3 role combinations and the community roles they map to.
   */
  public static function communityRolesProvider(): array {
    return [
      'steward' => [
        ['language steward'],
        ['community-community-community_manager'],
      ],
      'contributor and steward' => [
        ['language contributor', 'language steward'],
        ['community-community-member', 'community-community-community_manager'],
      ],
      'administrator member' => [
        ['administrator member'],
        ['community-community-community_manager'],
      ],
      'administrator member and steward' => [
        ['administrator member', 'language steward'],
        ['community-community-community_manager'],
      ],
      'contributor' => [
        ['language contributor'],
        ['community-community-member'],
      ],
      'no roles' => [
        [],
        [],
      ],
    ];
  }

  /**
   * Tests the community roles a language community member gets.
   */
  #[DataProvider('communityRolesProvider')]
  public function testCommunityRoles(array $v3_roles, array $expected): void {
    $this->assertSame($expected, $this->mapRoles('mukurtu_cms_v3_language_community_community_memberships', $v3_roles));
  }

  /**
   * Tests that every v3 role of a protocol member is kept.
   */
  public function testProtocolRolesAreMappedOneByOne(): void {
    $this->assertSame(
      ['protocol-protocol-language_contributor', 'protocol-protocol-language_steward'],
      $this->mapRoles('mukurtu_cms_v3_language_community_protocol_memberships', [
        'language contributor',
        'language steward',
      ])
    );
  }

}
