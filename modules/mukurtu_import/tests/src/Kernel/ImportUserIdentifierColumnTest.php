<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Tests MukurtuImportStrategy::getLabelSourceColumn() for the 'user' type.
 *
 * Regression coverage: the 'user' entity type has no 'label' entity key in
 * Drupal core (User::label() computes the account name dynamically), so
 * getLabelSourceColumn() always returned NULL for user imports regardless
 * of mapping, making CustomStrategyFromFileForm::validateForm() and
 * MukurtuImportStrategyForm::validateForm() hard-block saving a user
 * import mapping with no explicit Identifier Column, even when the
 * required username field was mapped -- unlike every other entity type,
 * where mapping the real label field (title/name) satisfies the same
 * check.
 *
 * @see \Drupal\mukurtu_import\Entity\MukurtuImportStrategy::getLabelSourceColumn()
 */
class ImportUserIdentifierColumnTest extends MukurtuImportTestBase {

  /**
   * Mapping the username field to 'name' satisfies getLabelSourceColumn().
   */
  public function testUsernameFieldSatisfiesLabelSourceColumn(): void {
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('user');
    $import_config->setTargetBundle('user');
    $import_config->setMapping([
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
    ]);

    $this->assertSame('Username', $import_config->getLabelSourceColumn());
  }

  /**
   * A user import mapping with no username, ID, or UUID column mapped
   * still returns NULL -- the fix doesn't remove the "must map something"
   * guardrail, it just fixes what satisfies it.
   */
  public function testUnmappedUsernameStillReturnsNull(): void {
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('user');
    $import_config->setTargetBundle('user');
    $import_config->setMapping([
      ['target' => 'mail', 'source' => 'Email'],
    ]);

    $this->assertNull($import_config->getLabelSourceColumn());
  }

  /**
   * Other entity types are unaffected -- still keyed off their real label
   * entity key (e.g. 'title' for node), not 'name'.
   */
  public function testNodeImportStillUsesTitleAsLabelSourceColumn(): void {
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('node');
    $import_config->setTargetBundle('protocol_aware_content');
    $import_config->setMapping([
      ['target' => 'title', 'source' => 'Title'],
    ]);

    $this->assertSame('Title', $import_config->getLabelSourceColumn());
  }

}
