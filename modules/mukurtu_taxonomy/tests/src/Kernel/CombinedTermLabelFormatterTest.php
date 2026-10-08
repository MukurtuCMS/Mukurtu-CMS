<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_taxonomy\Plugin\Field\FieldFormatter\CombinedTermLabelFormatter;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that identical term names in Other Names render once (#2290).
 */
#[CoversClass(CombinedTermLabelFormatter::class)]
#[Group('mukurtu_taxonomy')]
class CombinedTermLabelFormatterTest extends EntityKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'language',
    'content_translation',
    'mukurtu_taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);
    \Drupal::service('router.builder')->rebuild();

    foreach (['creator', 'contributor', 'people'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_other_names', 'Other Names', 'taxonomy_term', 'default', [
      'target_bundles' => ['creator' => 'creator', 'contributor' => 'contributor', 'people' => 'people'],
    ], -1);

    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->setUpCurrentUser([], ['access content', 'view test entity']);
  }

  /**
   * Creates a term in the given vocabulary.
   */
  private function createTerm(string $vid, string $name): TermInterface {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Builds the field with the formatter in the given language.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms
   *   The terms to reference, in field order.
   * @param array $settings
   *   The formatter settings.
   * @param string $langcode
   *   The language to render in.
   * @param string $formatter
   *   The formatter plugin ID.
   */
  private function buildField(array $terms, array $settings = [], string $langcode = 'en', string $formatter = 'mukurtu_combined_term_label'): array {
    $entity = EntityTest::create(['field_other_names' => array_map(fn(TermInterface $t) => $t->id(), $terms)]);
    $entity->save();
    $items = $entity->get('field_other_names');
    assert($items instanceof FieldItemListInterface);

    $instance = \Drupal::service('plugin.manager.field.formatter')->getInstance([
      'field_definition' => $items->getFieldDefinition(),
      'view_mode' => 'full',
      'configuration' => ['type' => $formatter, 'settings' => $settings],
    ]);
    $instance->prepareView([$entity->id() => $items]);
    return $instance->viewElements($items, $langcode);
  }

  /**
   * Returns the visible label of each element, in order.
   */
  private function labels(array $elements): array {
    return array_values(array_map(fn(array $e) => (string) ($e['#title'] ?? $e['#plain_text']), $elements));
  }

  /**
   * Identical names from different vocabularies collapse to the first term.
   */
  public function testIdenticalNamesCombine(): void {
    $creator = $this->createTerm('creator', 'Jane Doe');
    $contributor = $this->createTerm('contributor', ' jane   doe ');
    $john = $this->createTerm('people', 'John Roe');
    $people = $this->createTerm('people', 'Jane Doe');

    $elements = $this->buildField([$creator, $contributor, $john, $people]);

    $this->assertSame([0, 2], array_keys($elements));
    $this->assertSame(['Jane Doe', 'John Roe'], $this->labels($elements));
    $this->assertSame('link', $elements[0]['#type']);
    $this->assertSame($creator->toUrl()->toString(), $elements[0]['#url']->toString());

    // Editing any of the combined terms must still invalidate the output.
    foreach ([$creator, $contributor, $people] as $term) {
      $this->assertContains('taxonomy_term:' . $term->id(), $elements[0]['#cache']['tags']);
    }
    $this->assertNotContains('taxonomy_term:' . $john->id(), $elements[0]['#cache']['tags']);
  }

  /**
   * With linking turned off, the combined entry is plain text.
   */
  public function testUnlinkedOutput(): void {
    $elements = $this->buildField([
      $this->createTerm('creator', 'Jane Doe'),
      $this->createTerm('people', 'Jane Doe'),
    ], ['link' => FALSE]);

    $this->assertCount(1, $elements);
    $this->assertSame('Jane Doe', $elements[0]['#plain_text']);
    $this->assertArrayNotHasKey('#type', $elements[0]);
  }

  /**
   * Distinct names render exactly as core's label formatter renders them.
   */
  public function testDistinctNamesMatchCoreFormatter(): void {
    $terms = [
      $this->createTerm('creator', 'Jane Doe'),
      $this->createTerm('contributor', 'Janet Doe'),
      $this->createTerm('people', 'John Roe'),
    ];

    // Each build's term and URL objects point back at its own host entity,
    // so compare what gets rendered rather than the objects themselves.
    $strip = fn(array $elements) => array_map(fn(array $e) => [
      '#type' => $e['#type'] ?? NULL,
      '#title' => $e['#title'] ?? $e['#plain_text'],
      '#url' => isset($e['#url']) ? $e['#url']->toString() : NULL,
      '#cache' => $e['#cache'],
    ], $elements);
    $this->assertEquals(
      $strip($this->buildField($terms, [], 'en', 'entity_reference_label')),
      $strip($this->buildField($terms)),
    );
  }

  /**
   * Names are compared in the language being rendered.
   */
  public function testComparesTranslatedLabels(): void {
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', 'creator', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', 'people', TRUE);

    $creator = $this->createTerm('creator', 'Jane Doe');
    $creator->addTranslation('es', ['name' => 'Juana Doe'])->save();
    $people = $this->createTerm('people', 'Juana Doe');

    $this->assertSame(['Jane Doe', 'Juana Doe'], $this->labels($this->buildField([$creator, $people])));
    $this->assertSame(['Juana Doe'], $this->labels($this->buildField([$creator, $people], [], 'es')));
  }

  /**
   * The formatter is only offered for taxonomy term references.
   */
  public function testOnlyApplicableToTermReferences(): void {
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_users', 'Users', 'user');

    $this->assertTrue(CombinedTermLabelFormatter::isApplicable(FieldConfig::loadByName('entity_test', 'entity_test', 'field_other_names')));
    $this->assertFalse(CombinedTermLabelFormatter::isApplicable(FieldConfig::loadByName('entity_test', 'entity_test', 'field_users')));
  }

}
