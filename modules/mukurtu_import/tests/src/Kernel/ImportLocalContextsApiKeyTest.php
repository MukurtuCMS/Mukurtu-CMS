<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;

/**
 * Test importing the multi-value Local Contexts API key field.
 */
class ImportLocalContextsApiKeyTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The base test user is only a plain community member (parent::setUp()
    // already created that membership); grant it the community_manager role
    // on its existing membership so the import destination's entity access
    // check (which the base setup never needed, since it only imports onto
    // nodes) allows updating the community itself. Community::addMember()
    // no-ops when a membership already exists, so the role must be set
    // directly on the existing membership rather than via addMember().
    $role = OgRole::create([
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => ['update group'],
    ]);
    $role->setGroupType('community');
    $role->setGroupBundle('community');
    $role->save();

    $membership = Og::getMembership($this->community, $this->currentUser);
    $membership->setRoles([$role]);
    $membership->save();
  }

  /**
   * Importing multiple delimited values should populate every delta,
   * trimmed of any whitespace around the delimiter.
   */
  public function testMultipleApiKeysAreImportedAndTrimmed() {
    $data = [
      ['id', 'Local Contexts API key'],
      [$this->community->id(), 'key-one; key-two'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'id', 'source' => 'id'],
      ['target' => 'field_local_contexts_api_key', 'source' => 'Local Contexts API key'],
    ];

    $result = $this->importCsvFile($import_file, $mapping, 'community', 'community');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_community = $this->entityTypeManager->getStorage('community')->load($this->community->id());
    $this->assertEquals(
      ['key-one', 'key-two'],
      array_column($updated_community->get('field_local_contexts_api_key')->getValue(), 'value')
    );
  }

}
