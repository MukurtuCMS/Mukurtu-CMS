<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\LanguageCodeHooks;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the notice shown when a language term has no language code.
 *
 * Digital heritage's field_language has auto_create TRUE, so a term made by
 * typing a new language into that form arrives with no code, and the person
 * who made it never saw the field. Text marked with such a term is announced
 * with the page language's rules, which is what SC 3.1.2 is about (#2354).
 *
 * The notice is help text on the field rather than a message, and only on an
 * existing term. The field's description ends "Leave blank if you're not
 * sure", which this codebase means: a community may not want to assert a
 * code, and a wrong code is worse than none. So these tests pin that it
 * appears where the choice is made and stays quiet otherwise.
 *
 * @see \Drupal\mukurtu_core\Hook\LanguageCodeHooks::formTaxonomyTermLanguageFormAlter()
 */
#[Group('mukurtu_core')]
class LanguageCodeMissingNoticeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'path_alias',
    'taxonomy',
    // mukurtu_core has to be installed, not just loaded: the notice is an
    // OOP #[Hook] implementation, so without the module enabled it is never
    // discovered and every assertion here would pass against a form the hook
    // never touched. geofield, leaflet and node come with it as the minimum
    // its base fields need, matching FormHooksGoogleTagContainerFormAlterTest.
    'geofield',
    'leaflet',
    'node',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'field', 'filter']);

    Vocabulary::create(['vid' => 'language', 'name' => 'Language'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_language_code',
      'entity_type' => 'taxonomy_term',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_language_code',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'language',
      'label' => 'Language code',
      'description' => 'The code that tells screen readers which language this is.',
    ])->save();

    // Without a form display carrying the field, the form renders nothing for
    // it and every assertion about its description passes on an empty string.
    EntityFormDisplay::create([
      'targetEntityType' => 'taxonomy_term',
      'bundle' => 'language',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_language_code', ['type' => 'string_textfield'])->save();
  }

  /**
   * Builds the term form for a language term.
   */
  protected function buildTermForm(Term $term): array {
    return $this->container->get('entity.form_builder')->getForm($term, 'default');
  }

  /**
   * Flattens a widget's #description to text, however it is structured.
   */
  protected function describedAs(array $form): string {
    $description = $form['field_language_code']['widget'][0]['value']['#description'] ?? '';
    if (!is_array($description)) {
      return (string) $description;
    }
    return (string) $this->container->get('renderer')->renderInIsolation($description);
  }

  /**
   * Tests that an existing term without a code says what that costs.
   */
  public function testExistingTermWithoutCodeShowsTheNotice(): void {
    $term = Term::create(['vid' => 'language', 'name' => 'Test language']);
    $term->save();

    $text = $this->describedAs($this->buildTermForm($term));

    $this->assertStringContainsString('no code yet', $text);
    $this->assertStringContainsString('page language', $text);
    // The field's own guidance must survive alongside it.
    $this->assertStringContainsString('tells screen readers', $text,
      "The field's configured description is kept, not replaced.");
  }

  /**
   * Tests that a term which has a code says nothing.
   */
  public function testTermWithCodeShowsNoNotice(): void {
    $term = Term::create([
      'vid' => 'language',
      'name' => 'Test language',
      'field_language_code' => 'mi',
    ]);
    $term->save();

    $text = $this->describedAs($this->buildTermForm($term));

    $this->assertStringNotContainsString('no code yet', $text);
    $this->assertStringContainsString('tells screen readers', $text);
  }

  /**
   * Tests that a new term says nothing.
   *
   * It has no code yet by definition, so the notice would be noise and would
   * read as a complaint about a form nobody has filled in.
   */
  public function testNewTermShowsNoNotice(): void {
    $term = Term::create(['vid' => 'language']);

    $text = $this->describedAs($this->buildTermForm($term));

    $this->assertStringNotContainsString('no code yet', $text);
  }

  /**
   * Tests that an unexpected widget shape is left alone rather than guessed at.
   */
  public function testUnexpectedWidgetShapeIsLeftAlone(): void {
    $form = ['field_language_code' => ['widget' => [0 => ['value' => []]]]];
    $formState = new FormState();
    $term = Term::create(['vid' => 'language', 'name' => 'Test language']);
    $term->save();
    $formState->setFormObject(
      $this->container->get('entity_type.manager')->getFormObject('taxonomy_term', 'default')->setEntity($term)
    );

    (new LanguageCodeHooks())->formTaxonomyTermLanguageFormAlter($form, $formState);

    $this->assertSame(['field_language_code' => ['widget' => [0 => ['value' => []]]]], $form,
      'A widget without a #description is left untouched.');
  }

}
