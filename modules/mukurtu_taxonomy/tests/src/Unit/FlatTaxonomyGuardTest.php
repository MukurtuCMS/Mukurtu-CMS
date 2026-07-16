<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the flat_taxonomy checkbox guard on the vocabulary edit form.
 *
 * @see mukurtu_taxonomy_form_taxonomy_vocabulary_form_alter()
 * @see mukurtu_taxonomy_lock_flat_taxonomy_checkbox()
 * @group mukurtu_taxonomy
 */
class FlatTaxonomyGuardTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../mukurtu_taxonomy.module';
  }

  /**
   * The alter attaches the #after_build callback without touching the form.
   *
   * It must not modify $form['flat'] directly, since flat_taxonomy's own
   * hook_form_taxonomy_vocabulary_form_alter() may not have run yet.
   */
  public function testAlterAttachesAfterBuildCallback(): void {
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);

    mukurtu_taxonomy_form_taxonomy_vocabulary_form_alter($form, $formState);

    $this->assertSame(['mukurtu_taxonomy_lock_flat_taxonomy_checkbox'], $form['#after_build']);
    $this->assertArrayNotHasKey('flat', $form);
  }

  /**
   * The #after_build callback locks an existing flat checkbox on.
   *
   * Covers both an existing vocabulary (flat already TRUE, matching every
   * stock Mukurtu vocabulary today) and a brand-new vocabulary (flat_taxonomy
   * defaults the checkbox to a falsy value for an unsaved vocabulary).
   *
   * @dataProvider defaultValueProvider
   */
  public function testLocksFlatCheckboxOn(bool $initialDefaultValue): void {
    $form = [
      'flat' => [
        '#type' => 'checkbox',
        '#default_value' => $initialDefaultValue,
      ],
    ];
    $formState = $this->createMock(FormStateInterface::class);

    $result = mukurtu_taxonomy_lock_flat_taxonomy_checkbox($form, $formState);

    $this->assertTrue($result['flat']['#default_value']);
    $this->assertTrue($result['flat']['#disabled']);
    $this->assertNotEmpty($result['flat']['#description']->getUntranslatedString());
  }

  /**
   * Data provider of the checkbox's #default_value before the guard runs.
   */
  public static function defaultValueProvider(): array {
    return [
      'existing flat vocabulary' => [TRUE],
      'new, unsaved vocabulary' => [FALSE],
    ];
  }

  /**
   * Forms without a 'flat' element are left untouched.
   *
   * This can't normally happen for taxonomy_vocabulary_form since
   * flat_taxonomy always adds the element, but the callback should be
   * defensive regardless.
   */
  public function testFormWithoutFlatElementIsLeftAlone(): void {
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);

    $result = mukurtu_taxonomy_lock_flat_taxonomy_checkbox($form, $formState);

    $this->assertSame([], $result);
  }

}
