<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_media\Entity\Audio;
use Drupal\mukurtu_media\Entity\Document;
use Drupal\mukurtu_media\Entity\ExternalEmbed;
use Drupal\mukurtu_media\Entity\Image;
use Drupal\mukurtu_media\Entity\RemoteVideo;
use Drupal\mukurtu_media\Entity\SoundCloud;
use Drupal\mukurtu_media\Entity\Video;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the "Identifier" field label is translatable across the 7
 * media bundle classes that duplicated it as a plain string.
 */
#[Group('mukurtu_media')]
class MediaFieldLabelTest extends ProtocolAwareEntityTestBase {

  /**
   */
  #[DataProvider('mediaBundleClassProvider')]
  public function testIdentifierFieldLabelIsTranslatable(string $class): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('media');
    $definitions = $class::bundleFieldDefinitions($entityType, '', []);

    $label = $definitions['field_identifier']->getLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('Identifier', (string) $label);
  }

  public static function mediaBundleClassProvider(): array {
    return [
      'Document' => [Document::class],
      'Audio' => [Audio::class],
      'Image' => [Image::class],
      'RemoteVideo' => [RemoteVideo::class],
      'Video' => [Video::class],
      'SoundCloud' => [SoundCloud::class],
      'ExternalEmbed' => [ExternalEmbed::class],
    ];
  }

  /**
   * ExternalEmbed also duplicated its own bundle-specific field's label.
   */
  public function testExternalEmbedFieldLabelIsTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('media');
    $definitions = ExternalEmbed::bundleFieldDefinitions($entityType, '', []);

    $label = $definitions['field_media_external_embed']->getLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('External Embed', (string) $label);
  }

}
