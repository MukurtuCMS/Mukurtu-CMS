<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the flat_taxonomy checkbox guard reaches the rendered HTML.
 *
 * A unit test calling mukurtu_taxonomy_lock_flat_taxonomy_checkbox()
 * directly can only assert on the #disabled render-array property, not on
 * whether it actually reaches the HTML `disabled` attribute - that
 * conversion happens inside FormBuilder::doBuildForm()/handleInputElement(),
 * which only a real form build exercises. An earlier version of this guard
 * attached via #after_build, which runs after that conversion has already
 * happened for every child element, so #disabled never reached the HTML
 * even though the render array looked correct (see issue #1788 follow-up).
 * This test builds a real form through the actual form-building pipeline to
 * catch that class of regression.
 *
 * @see mukurtu_taxonomy_form_taxonomy_vocabulary_form_alter()
 * @see mukurtu_taxonomy_lock_flat_taxonomy_checkbox()
 * @group mukurtu_taxonomy
 */
class FlatTaxonomyCheckboxRenderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once __DIR__ . '/../../../mukurtu_taxonomy.module';
  }

  /**
   * Building a form with the guard's #process callback locks the checkbox.
   */
  public function testProcessCallbackDisablesRenderedCheckbox(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(FlatTaxonomyCheckboxRenderTestForm::class, $form_state);

    $this->assertSame('disabled', $form['flat']['#attributes']['disabled'] ?? NULL);
    $this->assertTrue($form['flat']['#default_value']);
  }

}

/**
 * Minimal test form standing in for taxonomy_vocabulary_form.
 *
 * Reproduces just the piece of taxonomy_vocabulary_form that matters here:
 * a 'flat' checkbox (as added by the contrib flat_taxonomy module) plus our
 * guard's #process callback, without pulling in flat_taxonomy or the rest
 * of mukurtu_taxonomy's heavy dependency chain.
 */
class FlatTaxonomyCheckboxRenderTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flat_taxonomy_checkbox_render_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['flat'] = [
      '#type' => 'checkbox',
      '#title' => 'Flat taxonomy',
      '#default_value' => FALSE,
    ];
    $form['#process'][] = 'mukurtu_taxonomy_lock_flat_taxonomy_checkbox';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
