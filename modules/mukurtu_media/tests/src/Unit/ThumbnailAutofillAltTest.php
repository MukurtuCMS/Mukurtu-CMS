<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the thumbnail alt text auto-fill AJAX helpers.
 *
 * @see mukurtu_media_thumbnail_autofill_alt()
 * @see mukurtu_media_thumbnail_alt_process()
 */
#[Group('mukurtu_media')]
class ThumbnailAutofillAltTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../mukurtu_media.module';
  }

  /**
   * The process callback redirects the upload button's AJAX callback.
   */
  public function testProcessAttachesUploadCallback(): void {
    $element = ['upload_button' => ['#ajax' => ['callback' => 'some_default_callback']]];
    $formState = $this->createMock(FormStateInterface::class);

    $result = mukurtu_media_thumbnail_alt_process($element, $formState, $element);

    $this->assertSame('mukurtu_media_thumbnail_upload_ajax_callback', $result['upload_button']['#ajax']['callback']);
  }

  /**
   * The process callback is a no-op when there's no upload button AJAX.
   */
  public function testProcessLeavesElementWithoutAjaxAlone(): void {
    $element = ['upload_button' => []];
    $formState = $this->createMock(FormStateInterface::class);

    $result = mukurtu_media_thumbnail_alt_process($element, $formState, $element);

    $this->assertArrayNotHasKey('#ajax', $result['upload_button']);
  }

  /**
   * Existing alt text is left untouched, on both standalone and modal forms.
   */
  #[DataProvider('elementParentsProvider')]
  public function testExistingAltTextIsPreserved(string $elementParents, array $form): void {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->never())->method('getValue');
    $request = Request::create('/ajax', 'GET', ['element_parents' => $elementParents]);

    mukurtu_media_thumbnail_autofill_alt($form, $formState, $request);

    $widget = $this->getThumbnailWidget($form, $elementParents);
    $this->assertSame('Existing alt text', $widget['alt']['#value']);
  }

  /**
   * Alt text is filled from the sibling Name field when it has a value.
   */
  #[DataProvider('elementParentsProvider')]
  public function testAltFilledFromName(string $elementParents, array $form): void {
    $form = $this->withEmptyAlt($form, $elementParents);
    $nameParents = array_merge(array_slice(explode('/', $elementParents), 0, -3), ['name', 0, 'value']);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->with($nameParents)->willReturn('My Media Title');
    $request = Request::create('/ajax', 'GET', ['element_parents' => $elementParents]);

    mukurtu_media_thumbnail_autofill_alt($form, $formState, $request);

    $widget = $this->getThumbnailWidget($form, $elementParents);
    $this->assertSame('My Media Title', $widget['alt']['#value']);
  }

  /**
   * Alt text falls back to the thumbnail's own filename when Name is empty.
   */
  #[DataProvider('elementParentsProvider')]
  public function testAltFallsBackToFilename(string $elementParents, array $form): void {
    $form = $this->withEmptyAlt($form, $elementParents);
    $formParents = explode('/', $elementParents);
    $widget = &$this->getThumbnailWidgetReference($form, $formParents);
    $widget['#value']['fids'] = [42];

    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('screenshot.png');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn($file);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('file')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);
    \Drupal::setContainer($container);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturn('');
    $request = Request::create('/ajax', 'GET', ['element_parents' => $elementParents]);

    mukurtu_media_thumbnail_autofill_alt($form, $formState, $request);

    $result = $this->getThumbnailWidget($form, $elementParents);
    $this->assertSame('screenshot', $result['alt']['#value']);
  }

  /**
   * Element parents for the standalone add/edit form vs. the media library
   * modal, where the thumbnail widget is nested under media/{delta}/fields/.
   */
  public static function elementParentsProvider(): array {
    return [
      'standalone form' => [
        'field_thumbnail/widget/0',
        [
          'field_thumbnail' => ['widget' => [0 => ['alt' => ['#value' => 'Existing alt text']]]],
          'name' => [0 => ['value' => ['#value' => '']]],
        ],
      ],
      'media library modal' => [
        'media/0/fields/field_thumbnail/widget/0',
        [
          'media' => [
            0 => [
              'fields' => [
                'field_thumbnail' => ['widget' => [0 => ['alt' => ['#value' => 'Existing alt text']]]],
                'name' => [0 => ['value' => ['#value' => '']]],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Returns a copy of $form with the thumbnail widget's alt value cleared.
   */
  protected function withEmptyAlt(array $form, string $elementParents): array {
    $formParents = explode('/', $elementParents);
    $widget = &$this->getThumbnailWidgetReference($form, $formParents);
    $widget['alt']['#value'] = '';
    return $form;
  }

  /**
   * Reads the thumbnail widget sub-array out of $form by value.
   */
  protected function getThumbnailWidget(array $form, string $elementParents): array {
    $formParents = explode('/', $elementParents);
    return $this->getThumbnailWidgetReference($form, $formParents);
  }

  /**
   * Reads the thumbnail widget sub-array out of $form by reference.
   */
  protected function &getThumbnailWidgetReference(array &$form, array $formParents) {
    $ref = &$form;
    foreach ($formParents as $parent) {
      $ref = &$ref[$parent];
    }
    return $ref;
  }

}
