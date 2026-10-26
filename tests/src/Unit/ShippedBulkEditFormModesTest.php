<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the shipped bulk_edit form modes leave out what they must.
 *
 * These forms decide what bulk field editing can change. Protocols have their
 * own bulk actions, moderation state has its own, and the rest are settings,
 * single-item details, or widgets that don't work for many items at once.
 */
#[Group('mukurtu')]
class ShippedBulkEditFormModesTest extends UnitTestCase {

  /**
   * Components that must never appear in a bulk_edit form.
   */
  private const EXCLUDED = [
    'title',
    'langcode',
    'uid',
    'created',
    'path',
    'promote',
    'sticky',
    'status',
    'revision_log',
    'moderation_state',
    'comment',
    'field_cultural_protocols',
    'field_protocol_control',
    'url_redirects',
    'field_items_in_collection',
    'field_child_collections',
    'field_words',
    'field_mukurtu_community_records',
  ];

  /**
   * Widgets that must never appear in a bulk_edit form.
   */
  private const EXCLUDED_WIDGETS = ['paragraphs', 'entity_reference_paragraphs', 'comment_default'];

  /**
   * Each content type's bulk_edit form and the module that ships it.
   */
  public static function formProvider(): \Generator {
    yield 'digital heritage' => ['modules/mukurtu_digital_heritage', 'digital_heritage'];
    yield 'dictionary word' => ['modules/mukurtu_dictionary', 'dictionary_word'];
    yield 'word list' => ['modules/mukurtu_dictionary', 'word_list'];
    yield 'collection' => ['modules/mukurtu_collection', 'collection'];
    yield 'person' => ['modules/mukurtu_person', 'person'];
    yield 'place' => ['modules/mukurtu_place', 'place'];
  }

  /**
   * The form exists, is enabled, and leaves out the excluded components.
   */
  #[DataProvider('formProvider')]
  public function testBulkEditForm(string $module, string $type): void {
    $root = dirname(__DIR__, 3);
    $path = "$root/$module/config/install/core.entity_form_display.node.$type.bulk_edit.yml";
    $this->assertFileExists($path);
    $display = Yaml::parseFile($path);

    $this->assertTrue($display['status']);
    $this->assertSame('bulk_edit', $display['mode']);
    $this->assertContains('core.entity_form_mode.node.bulk_edit', $display['dependencies']['config']);
    $this->assertNotEmpty($display['content']);
    foreach ($display['content'] as $name => $component) {
      $this->assertNotContains($name, self::EXCLUDED, "$type's bulk_edit form offers $name.");
      $this->assertStringStartsNotWith('field_mukurtu_protocol', $name, "$type's bulk_edit form offers $name.");
      $this->assertNotContains($component['type'] ?? '', self::EXCLUDED_WIDGETS, "$type's bulk_edit form uses the {$component['type']} widget for $name.");
    }
    $this->assertArrayNotHasKey('field_group', $display['third_party_settings'] ?? [], "$type's bulk_edit form has field groups.");
  }

  /**
   * The form mode itself ships with mukurtu_core.
   */
  public function testFormModeShips(): void {
    $root = dirname(__DIR__, 3);
    $mode = Yaml::parseFile("$root/modules/mukurtu_core/config/install/core.entity_form_mode.node.bulk_edit.yml");
    $this->assertSame('node.bulk_edit', $mode['id']);
    $this->assertSame('node', $mode['targetEntityType']);
  }

}
