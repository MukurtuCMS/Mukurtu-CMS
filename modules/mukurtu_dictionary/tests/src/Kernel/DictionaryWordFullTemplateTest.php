<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Template\Attribute;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the full dictionary word template in the mukurtu_v4 theme.
 *
 * The template prints the "Additional Information" group three different ways
 * depending on how many media assets the word has. Related content lives in
 * that group, so each way must still print it.
 */
#[Group('mukurtu_dictionary')]
class DictionaryWordFullTemplateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Renders the template with the given number of media assets.
   */
  private function renderTemplate(int $media_count): string {
    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    $template = file_get_contents($this->root . '/' . $theme_path . '/templates/content/node--dictionary-word--full.html.twig');

    $media = ['#items' => array_fill(0, $media_count, [])];
    for ($i = 0; $i < $media_count; $i++) {
      $media[$i] = ['#markup' => "<p>Media asset $i</p>"];
    }

    $build = [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => [
        'attributes' => new Attribute(),
        'content_attributes' => new Attribute(),
        'title_prefix' => [],
        'title_suffix' => [],
        'node' => ['id' => 1, 'title' => ['value' => 'aloha']],
        'additional_word_entry_indices' => [],
        'thumbnail_display' => ['#markup' => '<p>Thumbnails</p>'],
        'content' => [
          'group_primary_fields' => [
            'group_additional_fields' => [
              'field_media_assets' => $media,
              'field_coverage_description' => ['#markup' => '<p>Coverage description</p>'],
              'field_all_related_content' => ['#markup' => '<p>Related content marker</p>'],
            ],
          ],
        ],
      ],
    ];
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Related content is printed however many media assets the word has.
   */
  #[DataProvider('mediaCountProvider')]
  public function testRelatedContentIsPrinted(int $media_count, bool $carousels): void {
    $html = $this->renderTemplate($media_count);

    // Confirms which branch of the template ran.
    $this->assertSame($carousels, str_contains($html, 'media-carousels'));
    $this->assertStringContainsString('Coverage description', $html);
    $this->assertStringContainsString('Related content marker', $html);
  }

  /**
   * Media asset counts that select each branch of the template.
   */
  public static function mediaCountProvider(): array {
    return [
      'no media' => [0, FALSE],
      'one media asset' => [1, FALSE],
      'several media assets' => [2, TRUE],
    ];
  }

}
