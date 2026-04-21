<?php

namespace Drupal\Tests\paragraphs\Kernel\migrate;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test Migration of paragraph and field collection bundles.
 *
 * @group paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('paragraphs')]
class ParagraphsTypeMigrationTest extends ParagraphsMigrationTestBase {

  /**
   * Test if the paragraph/fc types were brought over as a paragraph.
   */
  public function testParagraphsTypeMigration() {
    $this->executeMigration('d7_field_collection_type');
    $this->executeMigration('d7_paragraphs_type');

    $this->assertParagraphBundleExists('field_collection_test', 'Field collection test');
    $this->assertParagraphBundleExists('paragraph_bundle_one', 'Paragraph Bundle One');
    $this->assertParagraphBundleExists('paragraph_bundle_two', 'Paragraph Bundle Two');
  }

}
