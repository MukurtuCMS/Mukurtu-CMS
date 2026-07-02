<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the guard added to mukurtu_media_form_alter() for empty media
 * library result sets.
 *
 * Drupal core's MediaLibrarySelectForm::viewsFormValidate() calls
 * array_filter($form_state->getValue('media_library_select_form')) without a
 * NULL check. When the underlying view returns zero rows, no checkboxes are
 * added to the form and getValue() returns NULL instead of an array, so
 * array_filter(NULL) throws a TypeError. mukurtu_media_form_alter() prepends
 * a #validate handler that normalizes this value first.
 *
 * @group mukurtu_media
 */
class MediaLibrarySelectFormGuardTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../mukurtu_media.module';
  }

  /**
   * @covers ::mukurtu_media_form_alter
   */
  public function testFormAlterPrependsGuardForMediaLibraryWidgetForms(): void {
    $form = ['#validate' => ['::validateForm']];
    $form_state = new FormState();

    mukurtu_media_form_alter($form, $form_state, 'views_form_media_library_widget_table_image');

    $this->assertSame(
      '_mukurtu_media_normalize_media_library_select_form',
      $form['#validate'][0],
      'Guard handler must be prepended so it runs before the crashing core handler.'
    );
  }

  /**
   * @covers ::mukurtu_media_form_alter
   */
  public function testFormAlterIgnoresUnrelatedForms(): void {
    $form = ['#validate' => ['::validateForm']];
    $form_state = new FormState();

    mukurtu_media_form_alter($form, $form_state, 'some_other_form');

    $this->assertSame(['::validateForm'], $form['#validate']);
  }

  /**
   * @covers ::_mukurtu_media_normalize_media_library_select_form
   */
  public function testNormalizeConvertsNullToEmptyArray(): void {
    $form = [];
    $form_state = new FormState();
    // Simulate zero rows: no value was ever set for this key, so getValue()
    // returns NULL.
    $this->assertNull($form_state->getValue('media_library_select_form'));

    _mukurtu_media_normalize_media_library_select_form($form, $form_state);

    $this->assertSame([], $form_state->getValue('media_library_select_form'));
  }

  /**
   * @covers ::_mukurtu_media_normalize_media_library_select_form
   */
  public function testNormalizeLeavesExistingArrayIntact(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('media_library_select_form', [0 => '1']);

    _mukurtu_media_normalize_media_library_select_form($form, $form_state);

    $this->assertSame([0 => '1'], $form_state->getValue('media_library_select_form'));
  }

}
