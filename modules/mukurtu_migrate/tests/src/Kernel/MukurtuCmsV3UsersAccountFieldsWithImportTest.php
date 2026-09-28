<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\user\Plugin\migrate\destination\EntityUser;
use PHPUnit\Framework\Attributes\Group;

/**
 * Re-runs the account-field assertions with mukurtu_import installed.
 *
 * The mukurtu_import module replaces the destination class of several
 * entity:* migrate destinations in hook_migrate_destination_info_alter(). It
 * used to do that for entity:user too, which silently put the CSV import
 * wizard's rules (uid 1 can't be touched, passwords always stripped, new
 * accounts forced active) in front of the Mukurtu 3 to 4 user migrations.
 * That broke all three things the parent class asserts, and nothing caught
 * it because no mukurtu_migrate test had the module installed.
 *
 * Every test method is inherited: if the swap comes back, this class fails
 * while the parent still passes, which points straight at the cause.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareUserContent
 * @see mukurtu_import_migrate_destination_info_alter()
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersAccountFieldsWithImportTest extends MukurtuCmsV3UsersAccountFieldsTest {

  /**
   * {@inheritdoc}
   *
   * Drupal merges $modules up the class hierarchy, so these are added to the
   * parent's list. mukurtu_import's own dependencies have to be listed
   * explicitly: a Kernel test never resolves a module's info.yml
   * dependencies for it.
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'entity_test',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
    'media',
    'migrate_source_csv',
    'mukurtu_core',
    'mukurtu_import',
    'mukurtu_protocol',
    'node',
    'node_access_test',
    'og',
    'options',
    'taxonomy',
    'text',
    'views',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Saving a user with og and mukurtu_protocol installed touches these.
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('node');
  }

  /**
   * Tests that entity:user is still core's destination with import enabled.
   *
   * The parent's assertions all fail if this regresses; this one names the
   * cause outright.
   */
  public function testEntityUserDestinationIsCoreClass(): void {
    $definition = $this->container->get('plugin.manager.migrate.destination')
      ->getDefinition('entity:user');
    $this->assertSame(EntityUser::class, $definition['class'], 'mukurtu_import is overriding the entity:user destination again, which puts the CSV import wizard rules in front of the v3 user migrations.');
  }

}
