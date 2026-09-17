<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the block_content edit form hides revision information.
 *
 * block_content's entity type hardcodes show_revision_ui = TRUE in core
 * (\Drupal\block_content\Entity\BlockContent), so
 * ContentEntityForm::addRevisionableFormFields() always adds the
 * "Revision information" details and its "Create new revision" checkbox
 * regardless of the bundle's own `revision` setting - and every Mukurtu
 * block_content bundle ships with revision: false, so the section is never
 * meaningful in this codebase.
 */
#[Group('mukurtu_gin_custom')]
class BlockContentRevisionInfoHiddenTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'field',
    'block_content',
    'mukurtu_gin_custom',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installSchema('system', ['sequences']);

    // No body field needed - only the info base field (required on every
    // block_content bundle regardless of type config) is exercised by
    // getForm() below.
    BlockContentType::create([
      'id' => 'test_block_type',
      'label' => 'Test block type',
      'revision' => FALSE,
    ])->save();
  }

  /**
   * Tests the revision details and checkbox are both inaccessible.
   */
  public function testRevisionInformationHidden(): void {
    $entity = BlockContent::create([
      'type' => 'test_block_type',
      'info' => 'Test block',
    ]);
    $entity->save();

    $form = \Drupal::service('entity.form_builder')->getForm($entity, 'edit');

    $this->assertArrayHasKey('revision_information', $form);
    $this->assertFalse($form['revision_information']['#access']);
    $this->assertArrayHasKey('revision', $form);
    $this->assertFalse($form['revision']['#access']);
  }

}
