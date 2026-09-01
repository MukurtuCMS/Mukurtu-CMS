<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol_sync\Kernel;

use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that media attached only through a sample-sentence recording
 * (field_sentence_recording, nested inside a sample_sentence paragraph via
 * field_sample_sentences) inherits its parent node's cultural protocols the
 * same way media attached via a flat field (e.g. field_recording) already
 * does.
 *
 * Before this fix, _mukurtu_protocol_sync_sync_media_from_node() only ever
 * checked a fixed list of top-level node fields, so a dictionary word's
 * sample-sentence recording never got a protocol-parent claim or protocol
 * sync at all, regardless of its own "sync protocols" setting.
 */
#[Group('mukurtu_protocol_sync')]
class SampleSentenceProtocolSyncTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'file',
    'filter',
    'image',
    'node',
    'node_access_test',
    'key',
    'media',
    'media_library',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
    'user',
    'views',
    'mukurtu_core',
    'mukurtu_protocol',
    'entity_reference_revisions',
    'paragraphs',
    'mukurtu_media',
    'mukurtu_protocol_sync',
  ];

  protected function setUp(): void {
    parent::setUp();

    // mukurtu_protocol_sync's field_sync_protocols/field_protocol_parent
    // base fields are installed by its own hook_install() - KernelTestBase's
    // $modules list never runs that, so the module has to be really
    // installed for those fields to exist.
    $this->container->get('module_installer')->install(['mukurtu_protocol_sync']);

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('paragraph');
    $this->installSchema('file', ['file_usage']);

    MediaType::create(['id' => 'audio', 'label' => 'Audio', 'source' => 'audio_file', 'source_configuration' => ['source_field' => 'field_media_audio_file']])->save();

    ParagraphsType::create(['id' => 'sample_sentence', 'label' => 'Sample Sentence'])->save();
    FieldStorageConfig::create(['field_name' => 'field_sentence_recording', 'entity_type' => 'paragraph', 'type' => 'entity_reference', 'settings' => ['target_type' => 'media']])->save();
    FieldConfig::create(['field_name' => 'field_sentence_recording', 'entity_type' => 'paragraph', 'bundle' => 'sample_sentence', 'label' => 'Recording'])->save();

    FieldStorageConfig::create(['field_name' => 'field_sample_sentences', 'entity_type' => 'node', 'type' => 'entity_reference_revisions', 'settings' => ['target_type' => 'paragraph']])->save();
    FieldConfig::create(['field_name' => 'field_sample_sentences', 'entity_type' => 'node', 'bundle' => 'protocol_aware_content', 'label' => 'Sample Sentences'])->save();
  }

  public function testSampleSentenceRecordingInheritsParentProtocols(): void {
    $community = Community::create(['name' => 'Test Community']);
    $community->save();
    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();

    $media = Media::create([
      'bundle' => 'audio',
      'name' => 'Sentence recording',
      'status' => 0,
      'field_sync_protocols' => TRUE,
    ]);
    $media->save();

    $paragraph = Paragraph::create([
      'type' => 'sample_sentence',
      'field_sentence_recording' => $media->id(),
    ]);
    $paragraph->save();

    $node = Node::create([
      'title' => $this->randomString(),
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_sample_sentences' => [$paragraph],
    ]);
    $node->setProtocols([$protocol->id()]);
    $node->save();

    $media = Media::load($media->id());
    $this->assertSame($node->id(), $media->get('field_protocol_parent')->target_id, 'The sample-sentence recording media was claimed by the node as its protocol parent.');
    $this->assertNotEmpty($media->get('field_cultural_protocols')->protocols, 'The sample-sentence recording media inherited the node\'s cultural protocols.');
  }

  public function testSyncDisabledMediaIsNotClaimed(): void {
    $community = Community::create(['name' => 'Test Community']);
    $community->save();
    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();

    $media = Media::create([
      'bundle' => 'audio',
      'name' => 'Sentence recording',
      'status' => 0,
      'field_sync_protocols' => FALSE,
    ]);
    $media->save();

    $paragraph = Paragraph::create([
      'type' => 'sample_sentence',
      'field_sentence_recording' => $media->id(),
    ]);
    $paragraph->save();

    $node = Node::create([
      'title' => $this->randomString(),
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_sample_sentences' => [$paragraph],
    ]);
    $node->setProtocols([$protocol->id()]);
    $node->save();

    $media = Media::load($media->id());
    $this->assertNull($media->get('field_protocol_parent')->target_id, 'Media with sync disabled is left untouched, even when reachable through a sample sentence.');
  }

}
