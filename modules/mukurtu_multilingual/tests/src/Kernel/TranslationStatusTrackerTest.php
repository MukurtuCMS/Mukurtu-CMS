<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how translation status is worked out, stored and kept current.
 */
#[CoversClass(TranslationStatusTracker::class)]
#[Group('mukurtu_multilingual')]
class TranslationStatusTrackerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'file',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
    'mukurtu_multilingual',
  ];

  /**
   * The tracker service.
   */
  protected TranslationStatusTracker $tracker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_multilingual', [TranslationStatusTracker::TABLE]);
    $this->installConfig(['node', 'language']);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    ParagraphsType::create(['id' => 'sentence', 'label' => 'Sentence'])->save();

    $this->addTextField('node', 'article', 'field_description', TRUE);
    $this->addTextField('node', 'article', 'field_note', FALSE);
    $this->addTextField('paragraph', 'sentence', 'field_text', TRUE);

    FieldStorageConfig::create([
      'field_name' => 'field_sentences',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_sentences',
      'entity_type' => 'node',
      'bundle' => 'article',
      'translatable' => FALSE,
    ])->save();

    $manager = $this->container->get('content_translation.manager');
    $manager->setEnabled('node', 'article', TRUE);
    $manager->setEnabled('paragraph', 'sentence', TRUE);
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    $this->tracker = $this->container->get('mukurtu_multilingual.translation_status_tracker');
  }

  /**
   * Adds a plain text field to a bundle.
   */
  private function addTextField(string $entity_type, string $bundle, string $name, bool $translatable): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => 'string_long',
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'translatable' => $translatable,
    ])->save();
  }

  /**
   * Creates an English article with a title and description.
   */
  private function createArticle(array $values = []): NodeInterface {
    $node = Node::create($values + [
      'type' => 'article',
      'title' => 'Crow and fox',
      'field_description' => 'Master Crow, perched on a tree.',
      'langcode' => 'en',
    ]);
    $node->save();
    return $node;
  }

  /**
   * Reads one stored row.
   */
  private function row(NodeInterface $node, string $langcode): ?array {
    $row = \Drupal::database()->select(TranslationStatusTracker::TABLE, 's')
      ->fields('s', ['status', 'translated_count', 'total_count'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', $node->id())
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchAssoc();
    return $row ? array_map('intval', $row) : NULL;
  }

  /**
   * Asserts one stored row's status and counts.
   */
  private function assertStatus(NodeInterface $node, string $langcode, int $status, int $translated, int $total): void {
    $expected = ['status' => $status, 'translated_count' => $translated, 'total_count' => $total];
    $this->assertSame($expected, $this->row($node, $langcode), "Status row for $langcode.");
  }

  /**
   * With no translation, every language is "not translated".
   */
  public function testNoTranslation(): void {
    $node = $this->createArticle();

    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_NONE, 0, 2);
    $this->assertStatus($node, 'es', TranslationStatusTracker::STATUS_NONE, 0, 2);
    $this->assertNull($this->row($node, 'en'), 'The original language has no row.');
  }

  /**
   * A copied-but-unedited translation is still "not translated".
   */
  public function testUneditedTranslationCountsAsNotTranslated(): void {
    $node = $this->createArticle();
    $node->addTranslation('fr', $node->toArray())->save();

    $this->assertSame(TranslationStatusTracker::STATUS_NONE, $this->row($node, 'fr')['status']);
  }

  /**
   * Changing some fields makes it "partly translated".
   */
  public function testPartialTranslation(): void {
    $node = $this->createArticle();
    $node->addTranslation('fr', ['title' => 'Corbeau et renard'] + $node->toArray())->save();

    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_PARTIAL, 1, 2);
    $this->assertSame(TranslationStatusTracker::STATUS_NONE, $this->row($node, 'es')['status']);
  }

  /**
   * Changing every filled field makes it "translated".
   */
  public function testCompleteTranslation(): void {
    $node = $this->createArticle();
    $node->addTranslation('fr', [
      'title' => 'Corbeau et renard',
      'field_description' => 'Maître Corbeau, sur un arbre perché.',
    ] + $node->toArray())->save();

    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_COMPLETE, 2, 2);
  }

  /**
   * Emptying a field in the translation leaves it untranslated.
   */
  public function testEmptiedFieldIsNotTranslated(): void {
    $node = $this->createArticle();
    $node->addTranslation('fr', ['title' => 'Corbeau et renard', 'field_description' => NULL] + $node->toArray())->save();

    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_PARTIAL, 1, 2);
  }

  /**
   * Fields empty in the original, and untranslatable fields, don't count.
   */
  public function testEmptyAndUntranslatableFieldsAreIgnored(): void {
    $node = $this->createArticle(['field_description' => NULL, 'field_note' => 'Shared note']);
    $node->addTranslation('fr', ['title' => 'Corbeau et renard'] + $node->toArray())->save();

    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_COMPLETE, 1, 1);
  }

  /**
   * Translatable fields on referenced paragraphs count towards the host.
   */
  public function testParagraphFieldsCount(): void {
    $paragraph = Paragraph::create([
      'type' => 'sentence',
      'field_text' => 'The crow opens its beak.',
      'langcode' => 'en',
    ]);
    $paragraph->save();
    $node = $this->createArticle(['field_sentences' => [$paragraph]]);
    $this->assertSame(3, $this->row($node, 'fr')['total_count']);

    $node->addTranslation('fr', [
      'title' => 'Corbeau et renard',
      'field_description' => 'Maître Corbeau, sur un arbre perché.',
    ] + $node->toArray())->save();
    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_PARTIAL, 2, 3);

    $paragraph->addTranslation('fr', ['field_text' => 'Le corbeau ouvre un large bec.'])->save();
    $this->tracker->refresh(Node::load($node->id()));
    $this->assertStatus($node, 'fr', TranslationStatusTracker::STATUS_COMPLETE, 3, 3);
  }

  /**
   * Deleting an entity removes its rows.
   */
  public function testDeleteRemovesRows(): void {
    $node = $this->createArticle();
    $this->assertNotNull($this->row($node, 'fr'));

    $node->delete();
    $this->assertNull($this->row($node, 'fr'));
  }

  /**
   * Bundles without translation enabled aren't tracked.
   */
  public function testUntranslatableBundleIsNotTracked(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $node = Node::create(['type' => 'page', 'title' => 'About', 'langcode' => 'en']);
    $node->save();

    $this->assertNull($this->row($node, 'fr'));
  }

  /**
   * Adding a language queues one rebuild on cron, not one per change.
   */
  public function testAddingLanguageRebuildsOnCron(): void {
    $node = $this->createArticle();
    $state = $this->container->get('state');
    $state->delete(TranslationStatusTracker::REBUILD_STATE);

    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->assertNull($this->row($node, 'de'), 'Nothing is recalculated until cron runs.');
    $this->assertTrue($state->get(TranslationStatusTracker::REBUILD_STATE));

    $this->container->get('cron')->run();

    $this->assertFalse((bool) $state->get(TranslationStatusTracker::REBUILD_STATE));
    $this->assertSame(TranslationStatusTracker::STATUS_NONE, $this->row($node, 'de')['status']);
    $this->assertSame(0, $this->container->get('queue')->get(TranslationStatusTracker::QUEUE)->numberOfItems());
  }

}
