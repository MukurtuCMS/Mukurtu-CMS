<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SoundCloud media default name patch.
 *
 * SoundCloud media saved without a name (for example, from a roundtrip import
 * with no Name column) used to get "media:soundcloud:<uuid>" as the name. The
 * patches/media_entity_soundcloud-default-name.patch applied to
 * media_entity_soundcloud makes the source plugin return the oEmbed title
 * instead, without an extra HTTP request.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1388
 */
#[Group('mukurtu_core')]
class SoundcloudDefaultNameTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'media_entity_soundcloud',
    'system',
    'user',
  ];

  /**
   * The mocked HTTP responses, consumed in order.
   */
  protected MockHandler $mockHandler;

  /**
   * Requests sent through the mocked HTTP client.
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $requestHistory = [];

  /**
   * The SoundCloud media type.
   */
  protected MediaTypeInterface $mediaType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['field', 'system', 'image', 'file', 'media', 'media_entity_soundcloud']);

    // Swap in the mock client before the media type instantiates its source
    // plugin, which captures the client in its constructor.
    $this->mockHandler = new MockHandler();
    $stack = HandlerStack::create($this->mockHandler);
    $stack->push(Middleware::history($this->requestHistory));
    $this->container->set('http_client', new Client(['handler' => $stack]));

    $this->mediaType = $this->createMediaType('soundcloud', ['id' => 'soundcloud']);
  }

  /**
   * Saves SoundCloud media for a URL, optionally with a name.
   */
  protected function saveSoundcloudMedia(string $url, ?string $name = NULL): Media {
    $source_field = $this->mediaType->getSource()->getSourceFieldDefinition($this->mediaType)->getName();
    $values = [
      'bundle' => 'soundcloud',
      $source_field => $url,
    ];
    if ($name !== NULL) {
      $values['name'] = $name;
    }
    $media = Media::create($values);
    $media->save();
    return $media;
  }

  /**
   * Builds an oEmbed JSON response. Omits thumbnail_url to avoid downloads.
   */
  protected function oEmbedResponse(array $data): Response {
    return new Response(200, ['Content-Type' => 'application/json'], json_encode($data + [
      'html' => '<iframe src="https://w.soundcloud.com/player/?url=https%3A%2F%2Fapi.soundcloud.com%2Ftracks%2F123"></iframe>',
    ]));
  }

  /**
   * An unnamed item gets the oEmbed title, using a single oEmbed request.
   */
  public function testUnnamedMediaUsesOembedTitle(): void {
    $this->mockHandler->append($this->oEmbedResponse(['title' => '  Song of the River  ']));

    $media = $this->saveSoundcloudMedia('https://soundcloud.com/example/song-of-the-river');

    $this->assertSame('Song of the River', $media->getName());
    $this->assertSame('Song of the River', Media::load($media->id())->get('name')->value);
    // The name and the thumbnail share the statically cached oEmbed response.
    $this->assertCount(1, $this->requestHistory);
    $this->assertSame(0, $this->mockHandler->count());
  }

  /**
   * A name supplied by the editor or import is kept.
   */
  public function testExplicitNameIsKept(): void {
    $this->mockHandler->append($this->oEmbedResponse(['title' => 'Title from SoundCloud']));

    $media = $this->saveSoundcloudMedia('https://soundcloud.com/example/named-track', 'Name from import');

    $this->assertSame('Name from import', Media::load($media->id())->get('name')->value);
  }

  /**
   * A failed oEmbed lookup falls back to the generic default name.
   */
  public function testFailedOembedFallsBackToDefaultName(): void {
    $this->mockHandler->append(new Response(404));

    $media = $this->saveSoundcloudMedia('https://soundcloud.com/example/missing-track');

    $this->assertSame('media:soundcloud:' . $media->uuid(), Media::load($media->id())->get('name')->value);
  }

  /**
   * An oEmbed response without a usable title falls back to the default name.
   */
  public function testBlankOembedTitleFallsBackToDefaultName(): void {
    $this->mockHandler->append($this->oEmbedResponse(['title' => '   ']));

    $media = $this->saveSoundcloudMedia('https://soundcloud.com/example/untitled-track');

    $this->assertSame('media:soundcloud:' . $media->uuid(), Media::load($media->id())->get('name')->value);
  }

}
