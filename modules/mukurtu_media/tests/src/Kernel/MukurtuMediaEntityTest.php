<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\mukurtu_media\Entity\Audio;
use Drupal\mukurtu_media\Entity\AudioInterface;
use Drupal\mukurtu_media\Entity\Document;
use Drupal\mukurtu_media\Entity\DocumentInterface;
use Drupal\mukurtu_media\Entity\Image;
use Drupal\mukurtu_media\Entity\ImageInterface;
use Drupal\mukurtu_media\Entity\MukurtuFilenameGenerationInterface;
use Drupal\mukurtu_media\Entity\MukurtuThumbnailGenerationInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Tests the Mukurtu media entity bundle classes, interfaces, and field definitions.
 *
 * Covers: bundle class assignment via hook_entity_bundle_info_alter, interface
 * implementation per bundle, required vs optional fields, field cardinality,
 * auto_create settings for taxonomy reference fields, protocol field
 * persistence, and the ImageAltRequired constraint validator.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_media')]
class MukurtuMediaEntityTest extends MukurtuMediaTestBase {

  /**
   * Test that loading an audio entity returns the Audio bundle class and
   * implements all required interfaces.
   */
  public function testAudioBundleClassAndInterfaces(): void {
    $media = $this->buildMedia('audio', 'Audio Interface Test');
    $media->save();

    $loaded = Media::load($media->id());

    $this->assertInstanceOf(Audio::class, $loaded);
    $this->assertInstanceOf(AudioInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(PeopleInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuFilenameGenerationInterface::class, $loaded);
  }

  /**
   * Test that loading a document entity returns the Document bundle class and
   * implements all required interfaces.
   */
  public function testDocumentBundleClassAndInterfaces(): void {
    $media = $this->buildMedia('document', 'Document Interface Test');
    $media->save();

    $loaded = Media::load($media->id());

    $this->assertInstanceOf(Document::class, $loaded);
    $this->assertInstanceOf(DocumentInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(PeopleInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuFilenameGenerationInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuThumbnailGenerationInterface::class, $loaded);
  }

  /**
   * Test that loading an image entity returns the Image bundle class and
   * implements all required interfaces.
   */
  public function testImageBundleClassAndInterfaces(): void {
    $media = $this->buildMedia('image', 'Image Interface Test');
    $media->save();

    $loaded = Media::load($media->id());

    $this->assertInstanceOf(Image::class, $loaded);
    $this->assertInstanceOf(ImageInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(PeopleInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuFilenameGenerationInterface::class, $loaded);
  }

  /**
   * Test field required/optional status on the audio bundle.
   */
  public function testAudioFieldRequiredStatus(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'audio');

    // The name base field is required on all media.
    $this->assertTrue($definitions['name']->isRequired());

    // The audio source file is required.
    $this->assertTrue($definitions['field_media_audio_file']->isRequired());

    // Optional fields.
    $this->assertFalse($definitions['field_transcription']->isRequired());
    $this->assertFalse($definitions['field_contributor']->isRequired());
    $this->assertFalse($definitions['field_media_tags']->isRequired());
    $this->assertFalse($definitions['field_people']->isRequired());
    $this->assertFalse($definitions['field_thumbnail']->isRequired());
    $this->assertFalse($definitions['field_identifier']->isRequired());
  }

  /**
   * Test field required/optional status on the document bundle.
   */
  public function testDocumentFieldRequiredStatus(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'document');

    $this->assertTrue($definitions['field_media_document']->isRequired());

    $this->assertFalse($definitions['field_extracted_text']->isRequired());
    $this->assertFalse($definitions['field_media_tags']->isRequired());
    $this->assertFalse($definitions['field_people']->isRequired());
    $this->assertFalse($definitions['field_thumbnail']->isRequired());
    $this->assertFalse($definitions['field_identifier']->isRequired());
  }

  /**
   * Test field required/optional status on the image bundle.
   */
  public function testImageFieldRequiredStatus(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'image');

    $this->assertTrue($definitions['field_media_image']->isRequired());

    $this->assertFalse($definitions['field_media_tags']->isRequired());
    $this->assertFalse($definitions['field_people']->isRequired());
    $this->assertFalse($definitions['field_identifier']->isRequired());
  }

  /**
   * Test field cardinality on audio: multi-value taxonomy fields vs single-value fields.
   */
  public function testAudioFieldCardinality(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'audio');

    // Multi-value fields (cardinality = -1).
    $this->assertEquals(-1, $definitions['field_media_tags']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_contributor']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_people']->getFieldStorageDefinition()->getCardinality());

    // Single-value fields (cardinality = 1).
    $this->assertEquals(1, $definitions['field_media_audio_file']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_transcription']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_thumbnail']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_identifier']->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * Test auto_create settings: taxonomy reference fields on media bundles
   * should auto-create terms.
   */
  public function testAutoCreateSettings(): void {
    $audioDefinitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'audio');

    foreach (['field_media_tags', 'field_contributor', 'field_people'] as $field) {
      $this->assertTrue(
        $audioDefinitions[$field]->getSetting('handler_settings')['auto_create'],
        "$field on audio should auto-create taxonomy terms."
      );
    }

    $docDefinitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'document');

    foreach (['field_media_tags', 'field_people'] as $field) {
      $this->assertTrue(
        $docDefinitions[$field]->getSetting('handler_settings')['auto_create'],
        "$field on document should auto-create taxonomy terms."
      );
    }

    $imageDefinitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'image');

    foreach (['field_media_tags', 'field_people'] as $field) {
      $this->assertTrue(
        $imageDefinitions[$field]->getSetting('handler_settings')['auto_create'],
        "$field on image should auto-create taxonomy terms."
      );
    }
  }

  /**
   * Test that protocol sharing setting and protocol IDs are persisted on save.
   */
  public function testProtocolFieldPersistence(): void {
    $media = $this->buildMedia('audio', 'Protocol Persistence Test');
    $media->setSharingSetting('all');
    $media->setProtocols([$this->protocol]);
    $media->save();

    $loaded = Media::load($media->id());
    $this->assertEquals('all', $loaded->getSharingSetting());
    $this->assertContains((int) $this->protocol->id(), $loaded->getProtocols());
  }

  /**
   * Test the ImageAltRequired constraint fires when an image is uploaded
   * without alt text.
   *
   * field_media_image has alt_field_required=TRUE. The ImageAltRequired
   * constraint should report a violation when target_id is set but alt is empty.
   */
  public function testImageAltRequiredConstraint(): void {
    // Create a minimal File entity representing an uploaded image.
    // No actual file content is needed — only the entity record matters for
    // the constraint check (which inspects target_id and alt).
    $file = File::create([
      'uri' => 'private://test-image.jpg',
      'filename' => 'test-image.jpg',
      'filemime' => 'image/jpeg',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file->save();

    /** @var \Drupal\mukurtu_media\Entity\Image $media */
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Alt Test',
      'uid' => $this->currentUser->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => '',
      ],
    ]);
    $media->setSharingSetting('any');
    $media->setProtocols([$this->protocol]);

    $violations = $media->validate();

    $violationFields = [];
    foreach ($violations as $violation) {
      $violationFields[] = $violation->getPropertyPath();
    }

    $this->assertContains(
      'field_media_image.0.alt',
      $violationFields,
      'ImageAltRequired constraint should fire when image is uploaded without alt text.'
    );
  }

  /**
   * Test that alt text passes validation when provided.
   */
  public function testImageAltRequiredConstraintPassesWithAlt(): void {
    $file = File::create([
      'uri' => 'private://test-image-alt.jpg',
      'filename' => 'test-image-alt.jpg',
      'filemime' => 'image/jpeg',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file->save();

    /** @var \Drupal\mukurtu_media\Entity\Image $media */
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Alt Provided Test',
      'uid' => $this->currentUser->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'A descriptive alt text',
      ],
    ]);
    $media->setSharingSetting('any');
    $media->setProtocols([$this->protocol]);

    $violations = $media->validate();

    $violationFields = [];
    foreach ($violations as $violation) {
      $violationFields[] = $violation->getPropertyPath();
    }

    $this->assertNotContains(
      'field_media_image.0.alt',
      $violationFields,
      'ImageAltRequired constraint should not fire when alt text is provided.'
    );
  }

}
