<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Unit;

use Drupal\mukurtu_media\Entity\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the URI a generated PDF thumbnail is stored under.
 *
 * The thumbnail used to be placed by stripping the filename off the source URI
 * and re-joining a trailing slash. For a document at the root of a stream that
 * loses the scheme's second slash - 'public://notes.pdf' became 'public:',
 * then 'public:/notes_thumbnail.png' - which no stream wrapper resolves, so
 * the file landed nowhere and the browse card showed a broken image. Documents
 * in a subdirectory were unaffected, which is why this survived: uploads
 * normally carry a file_directory.
 *
 * @see \Drupal\mukurtu_media\Entity\Document::generateThumbnailFromFile()
 */
#[Group('mukurtu_media')]
#[CoversClass(Document::class)]
class DocumentSiblingUriTest extends TestCase {

  /**
   * URIs and the sibling they should produce.
   */
  public static function uriProvider(): array {
    return [
      'root of a public stream' => [
        'public://notes.pdf',
        'notes_thumbnail.png',
        'public://notes_thumbnail.png',
      ],
      'root of a private stream' => [
        'private://notes.pdf',
        'notes_thumbnail.png',
        'private://notes_thumbnail.png',
      ],
      'one subdirectory deep' => [
        'private://documents/report.pdf',
        'report_thumbnail.png',
        'private://documents/report_thumbnail.png',
      ],
      'several subdirectories deep' => [
        'public://2026/09/field-notes.pdf',
        'field-notes_thumbnail.png',
        'public://2026/09/field-notes_thumbnail.png',
      ],
      'filename containing a dollar sign' => [
        'public://cost$breakdown.pdf',
        'cost$breakdown_thumbnail.png',
        'public://cost$breakdown_thumbnail.png',
      ],
      'no slash at all' => [
        'notes.pdf',
        'notes_thumbnail.png',
        'notes_thumbnail.png',
      ],
    ];
  }

  /**
   * The sibling URI keeps the scheme and directory of the source.
   */
  #[DataProvider('uriProvider')]
  public function testSiblingUri(string $uri, string $filename, string $expected): void {
    $this->assertSame($expected, Document::siblingUri($uri, $filename));
  }

  /**
   * Every sibling URI a stream produces is still a resolvable stream URI.
   *
   * Guards the specific regression: the old code returned something that
   * looked URI-ish but carried a single slash, so getScheme() saw no scheme.
   */
  #[DataProvider('uriProvider')]
  public function testSchemeSurvives(string $uri, string $filename, string $expected): void {
    if (!str_contains($uri, '://')) {
      $this->assertStringNotContainsString(':', Document::siblingUri($uri, $filename));
      return;
    }

    $this->assertMatchesRegularExpression('@^[\w-]+://@', Document::siblingUri($uri, $filename));
  }

}
