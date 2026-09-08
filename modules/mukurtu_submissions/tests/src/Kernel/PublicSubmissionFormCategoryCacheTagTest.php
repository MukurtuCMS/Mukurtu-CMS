<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * The public submission form renders taxonomy-term reference fields (e.g.
 * Digital Heritage's field_category) with the core options_buttons widget,
 * whose option list is built straight from term storage and bubbles no list
 * cache tag. Without a taxonomy_term_list cache tag on the form, a cached
 * /submit/... page keeps serving a stale checkbox list after a Manager adds
 * or removes a category term. PublicSubmissionForm::buildForm() compensates
 * by attaching taxonomy_term_list:<vid> for every term-reference field the
 * bundle's "submission" display exposes.
 *
 * @group mukurtu_submissions
 */
class PublicSubmissionFormCategoryCacheTagTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_group',
    'file',
    'options',
    'path_alias',
    'node',
    'taxonomy',
    'text',
    'views',
    'mukurtu_submissions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');

    Vocabulary::create(['vid' => 'test_category', 'name' => 'Test Category'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_test_category',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_category',
      'entity_type' => 'node',
      'bundle' => static::TEST_BUNDLE,
      'label' => 'Test Category',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['test_category' => 'test_category']],
      ],
    ])->save();
  }

  /**
   * A term-reference field on the submission display adds its vocabulary's
   * list cache tag to the built form.
   */
  public function testTermReferenceFieldAddsListCacheTag(): void {
    $display = $this->container->get('entity_display.repository')
      ->getFormDisplay('node', static::TEST_BUNDLE, 'submission');
    $display->setComponent('field_test_category', ['type' => 'options_buttons'])->save();

    $form_object = \Drupal::classResolver(PublicSubmissionForm::class);
    $form = $form_object->buildForm([], new FormState(), 'node', static::TEST_BUNDLE);

    $this->assertContains('taxonomy_term_list:test_category', $form['#cache']['tags'] ?? []);
  }

  /**
   * A submission display with no term-reference field gets no
   * taxonomy_term_list cache tag.
   */
  public function testFormWithoutTermReferenceFieldHasNoListCacheTag(): void {
    // Ensure the "submission" display exists but never exposes the term field.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', static::TEST_BUNDLE, 'submission')
      ->removeComponent('field_test_category')
      ->save();

    $form_object = \Drupal::classResolver(PublicSubmissionForm::class);
    $form = $form_object->buildForm([], new FormState(), 'node', static::TEST_BUNDLE);

    foreach ($form['#cache']['tags'] ?? [] as $tag) {
      $this->assertStringStartsNotWith('taxonomy_term_list', $tag);
    }
  }

}
