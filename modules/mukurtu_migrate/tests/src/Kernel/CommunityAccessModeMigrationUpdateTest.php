<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40207().
 *
 * The Mukurtu 3 community and language community migrations could write the
 * protocol values "open" and "strict" to a community's field_access_mode,
 * which hid the community from everyone.
 *
 * @see mukurtu_migrate_update_40207()
 */
#[Group('mukurtu_migrate')]
class CommunityAccessModeMigrationUpdateTest extends KernelTestBase {

  /**
   * The language community mapping as it originally shipped.
   */
  const SHIPPED_LANGUAGE_COMMUNITY_MAPPING = [
    [
      'plugin' => 'static_map',
      'map' => [0 => 'open', 2 => 'strict'],
      'default_value' => 'open',
    ],
  ];

  /**
   * The community mapping as it originally shipped.
   */
  const SHIPPED_COMMUNITY_MAPPING = [
    [
      'plugin' => 'static_map',
      'source' => 'group_access',
      'map' => [0 => 'public', 2 => 'community-only'],
      'default_value' => 'strict',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'migrate',
    'migrate_plus',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';
  }

  /**
   * Saves a migration's access mode mapping to active config.
   */
  protected function setMapping(string $id, array $mapping): void {
    \Drupal::configFactory()->getEditable('migrate_plus.migration.' . $id)
      ->set('id', $id)
      ->set('process.field_access_mode', $mapping)
      ->save();
  }

  /**
   * Returns a migration's access mode mapping from active config.
   */
  protected function getMapping(string $id): mixed {
    return \Drupal::config('migrate_plus.migration.' . $id)->get('process.field_access_mode');
  }

  /**
   * Tests that the shipped mappings are replaced.
   */
  public function testShippedMappingsAreFixed(): void {
    $this->setMapping('mukurtu_cms_v3_language_communities', self::SHIPPED_LANGUAGE_COMMUNITY_MAPPING);
    $this->setMapping('mukurtu_cms_v3_communities', self::SHIPPED_COMMUNITY_MAPPING);

    mukurtu_migrate_update_40207();

    $this->assertSame([
      'plugin' => 'default_value',
      'default_value' => 'community-only',
    ], $this->getMapping('mukurtu_cms_v3_language_communities'));
    $expected = self::SHIPPED_COMMUNITY_MAPPING;
    $expected[0]['default_value'] = 'community-only';
    $this->assertSame($expected, $this->getMapping('mukurtu_cms_v3_communities'));
  }

  /**
   * Tests that customized mappings are left alone.
   */
  public function testCustomizedMappingsAreKept(): void {
    $language = [['plugin' => 'default_value', 'default_value' => 'public']];
    $community = self::SHIPPED_COMMUNITY_MAPPING;
    $community[0]['default_value'] = 'public';
    $this->setMapping('mukurtu_cms_v3_language_communities', $language);
    $this->setMapping('mukurtu_cms_v3_communities', $community);

    mukurtu_migrate_update_40207();

    $this->assertSame($language, $this->getMapping('mukurtu_cms_v3_language_communities'));
    $this->assertSame($community, $this->getMapping('mukurtu_cms_v3_communities'));
  }

  /**
   * Tests that a site without the migrations gets no new config.
   */
  public function testMissingMigrationsAreSkipped(): void {
    mukurtu_migrate_update_40207();

    $this->assertTrue(\Drupal::config('migrate_plus.migration.mukurtu_cms_v3_language_communities')->isNew());
    $this->assertTrue(\Drupal::config('migrate_plus.migration.mukurtu_cms_v3_communities')->isNew());
  }

  /**
   * Tests that the shipped migrations only write valid community values.
   */
  public function testShippedConfigWritesValidValues(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $read = fn(string $id) => Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.' . $id . '.yml'))['process']['field_access_mode'];
    $valid = ['public', 'community-only'];

    $language = $read('mukurtu_cms_v3_language_communities');
    $this->assertSame('default_value', $language['plugin']);
    $this->assertContains($language['default_value'], $valid);

    $community = $read('mukurtu_cms_v3_communities')[0];
    $this->assertContains($community['default_value'], $valid);
    foreach ($community['map'] as $value) {
      $this->assertContains($value, $valid);
    }
  }

}
