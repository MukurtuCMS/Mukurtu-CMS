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
    // Media: its name, the media file itself, and protocol sync.
    'name',
    'field_sync_protocols',
    'field_protocol_parent',
    'field_media_audio_file',
    'field_media_document',
    'field_media_external_embed',
    'field_media_image',
    'field_media_oembed_video',
    'field_media_soundcloud',
    'field_media_video_file',
    'field_media_video_tracks',
  ];

  /**
   * Dependencies that Drupal core ships rather than Mukurtu.
   */
  private const CORE_CONFIG = ['image.style.thumbnail'];

  /**
   * Widgets that must never appear in a bulk_edit form.
   */
  private const EXCLUDED_WIDGETS = ['paragraphs', 'entity_reference_paragraphs', 'comment_default'];

  /**
   * Each content and media type's bulk_edit form and the module that ships it.
   */
  public static function formProvider(): \Generator {
    yield 'digital heritage' => ['modules/mukurtu_digital_heritage', 'digital_heritage'];
    yield 'dictionary word' => ['modules/mukurtu_dictionary', 'dictionary_word'];
    yield 'word list' => ['modules/mukurtu_dictionary', 'word_list'];
    yield 'collection' => ['modules/mukurtu_collection', 'collection'];
    yield 'person' => ['modules/mukurtu_person', 'person'];
    yield 'place' => ['modules/mukurtu_place', 'place'];
    foreach (['audio', 'document', 'external_embed', 'image', 'remote_video', 'soundcloud', 'video'] as $type) {
      yield "$type media" => ['modules/mukurtu_media', $type, 'media'];
    }
  }

  /**
   * The form exists, is enabled, and leaves out the excluded components.
   */
  #[DataProvider('formProvider')]
  public function testBulkEditForm(string $module, string $type, string $entity_type = 'node'): void {
    $root = dirname(__DIR__, 3);
    $path = "$root/$module/config/install/core.entity_form_display.$entity_type.$type.bulk_edit.yml";
    $this->assertFileExists($path);
    $display = Yaml::parseFile($path);

    $this->assertTrue($display['status']);
    $this->assertSame('bulk_edit', $display['mode']);
    $this->assertContains("core.entity_form_mode.$entity_type.bulk_edit", $display['dependencies']['config']);
    $this->assertNotEmpty($display['content']);
    foreach ($display['content'] as $name => $component) {
      $this->assertNotContains($name, self::EXCLUDED, "$type's bulk_edit form offers $name.");
      $this->assertStringStartsNotWith('field_mukurtu_protocol', $name, "$type's bulk_edit form offers $name.");
      $this->assertNotContains($component['type'] ?? '', self::EXCLUDED_WIDGETS, "$type's bulk_edit form uses the {$component['type']} widget for $name.");
    }
    $this->assertArrayNotHasKey('field_group', $display['third_party_settings'] ?? [], "$type's bulk_edit form has field groups.");

    // A dependency nothing ships stops the site from installing.
    foreach (array_diff($display['dependencies']['config'] ?? [], self::CORE_CONFIG) as $dependency) {
      $this->assertNotEmpty(glob("$root/{config,modules/*/config}/install/$dependency.yml", GLOB_BRACE), "$type's bulk_edit form depends on $dependency, which no module ships.");
    }
  }

  /**
   * The form modes ship with mukurtu_core (content) and mukurtu_media.
   */
  public function testFormModeShips(): void {
    $root = dirname(__DIR__, 3);
    foreach (['node' => 'mukurtu_core', 'media' => 'mukurtu_media'] as $entity_type => $module) {
      $mode = Yaml::parseFile("$root/modules/$module/config/install/core.entity_form_mode.$entity_type.bulk_edit.yml");
      $this->assertSame("$entity_type.bulk_edit", $mode['id']);
      $this->assertSame($entity_type, $mode['targetEntityType']);
    }
  }

}
