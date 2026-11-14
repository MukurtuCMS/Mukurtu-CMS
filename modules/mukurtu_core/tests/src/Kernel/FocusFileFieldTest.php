<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\ManagedFileHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that file field AJAX responses keep keyboard focus in the field.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2648
 */
#[CoversClass(ManagedFileHooks::class)]
#[Group('mukurtu_core')]
class FocusFileFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'mukurtu_core',
  ];

  /**
   * The commands of an upload response from a media form.
   *
   * Like mukurtu_media_upload_ajax_callback(), it replaces other fields
   * first, then the file field's own AJAX wrapper.
   */
  protected const UPLOAD_RESPONSE = [
    [
      'command' => 'insert',
      'method' => 'replaceWith',
      'selector' => '#edit-name-wrapper > div',
      'data' => '<div id="edit-name-0">Name</div>',
    ],
    [
      'command' => 'insert',
      'method' => 'replaceWith',
      'selector' => NULL,
      'data' => '<div id="ajax-wrapper--X9YklH0IPKc"><details open>Video file</details></div>',
    ],
  ];

  /**
   * Runs hook_ajax_render_alter() for a request like the one given.
   *
   * @param array $commands
   *   The response's commands.
   * @param array $query
   *   The request's query parameters.
   * @param array $post
   *   The request's POST parameters.
   *
   * @return array
   *   The commands after the alter.
   */
  protected function alter(array $commands, array $query, array $post): array {
    $request = Request::create('/admin/media/add/video', 'POST', $post);
    $request->query->replace($query);
    $request_stack = $this->container->get('request_stack');
    $request->setSession($request_stack->getCurrentRequest()->getSession());
    $request_stack->push($request);

    $this->container->get('module_handler')->alter('ajax_render', $commands);
    return $commands;
  }

  /**
   * Tests that Upload and Remove responses focus the new wrapper last.
   */
  #[DataProvider('fileButtonProvider')]
  public function testFileButtonsGetTheCommand(string $trigger): void {
    $commands = $this->alter(
      static::UPLOAD_RESPONSE,
      ['element_parents' => 'field_media_video_file/widget/0', '_wrapper_format' => 'drupal_ajax'],
      ['_triggering_element_name' => $trigger],
    );

    $this->assertCount(3, $commands);
    $this->assertSame(static::UPLOAD_RESPONSE, array_slice($commands, 0, 2), 'The field is replaced before focus moves.');
    $this->assertSame(['command' => 'mukurtuFocusFileField', 'selector' => '#ajax-wrapper--X9YklH0IPKc'], $commands[2]);
  }

  /**
   * Provides triggering element names of file field buttons.
   */
  public static function fileButtonProvider(): array {
    return [
      'single upload' => ['field_media_video_file_0_upload_button'],
      'single remove' => ['field_media_video_file_0_remove_button'],
      'multiple remove' => ['field_media_video_tracks_1_remove_button'],
    ];
  }

  /**
   * Tests that other AJAX responses are left alone.
   */
  public function testOtherResponsesAreLeftAlone(): void {
    $query = ['element_parents' => 'field_media_video_file/widget/0'];
    $upload = ['_triggering_element_name' => 'field_media_video_file_0_upload_button'];

    // A remove button that isn't on a file field, like the import mapping.
    $this->assertSame(static::UPLOAD_RESPONSE, $this->alter(static::UPLOAD_RESPONSE, [], ['_triggering_element_name' => 'mapping_0_remove_button']));
    // Some other button in a file field's form.
    $this->assertSame(static::UPLOAD_RESPONSE, $this->alter(static::UPLOAD_RESPONSE, $query, ['_triggering_element_name' => 'op']));
    // An upload response that doesn't replace the field, like a refused one.
    $refused = [['command' => 'mukurtuResetRefusedUpload', 'message' => 'Too large']];
    $this->assertSame($refused, $this->alter($refused, $query, $upload));
  }

}
