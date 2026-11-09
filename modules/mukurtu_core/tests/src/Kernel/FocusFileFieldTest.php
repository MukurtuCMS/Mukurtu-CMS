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
  protected static $modules = ['system', 'user', 'file', 'mukurtu_core'];

  /**
   * Runs hook_ajax_render_alter() for a request like the one given.
   *
   * @param array $query
   *   The request's query parameters.
   * @param array $post
   *   The request's POST parameters.
   *
   * @return array
   *   The commands after the alter.
   */
  protected function alter(array $query, array $post): array {
    $request = Request::create('/system/ajax', 'POST', $post);
    $request->query->replace($query);
    $this->container->get('request_stack')->push($request);

    $commands = [['command' => 'insert', 'method' => 'replaceWith', 'selector' => NULL]];
    $this->container->get('module_handler')->alter('ajax_render', $commands);
    return $commands;
  }

  /**
   * Tests that Upload and Remove responses get the focus command last.
   */
  #[DataProvider('fileButtonProvider')]
  public function testFileButtonsGetTheCommand(string $trigger): void {
    $commands = $this->alter(
      ['element_parents' => 'field_media_video_file/widget/0', '_wrapper_format' => 'drupal_ajax'],
      ['_triggering_element_name' => $trigger],
    );

    $this->assertCount(2, $commands);
    $this->assertSame('insert', $commands[0]['command'], 'The field is replaced before focus moves.');
    $this->assertSame(['command' => 'mukurtuFocusFileField'], $commands[1]);
  }

  /**
   * Provides triggering element names of file field buttons.
   */
  public static function fileButtonProvider(): array {
    return [
      'single upload' => ['field_media_video_file_0_upload_button'],
      'single remove' => ['field_media_video_file_0_remove_button'],
      'multiple remove' => ['field_media_image_remove_button'],
    ];
  }

  /**
   * Tests that other AJAX responses are left alone.
   */
  public function testOtherResponsesAreLeftAlone(): void {
    // A remove button that isn't on a file field, like the import mapping.
    $not_a_file_field = $this->alter([], ['_triggering_element_name' => 'mapping_0_remove_button']);
    // Some other button in a file field's form.
    $other_button = $this->alter(['element_parents' => 'field_media_video_file/widget/0'], ['_triggering_element_name' => 'op']);

    $this->assertCount(1, $not_a_file_field);
    $this->assertCount(1, $other_button);
  }

}
