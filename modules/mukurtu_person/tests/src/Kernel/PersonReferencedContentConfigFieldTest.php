<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_core\Event\RelatedContentProvenanceEvent;
use Drupal\mukurtu_taxonomy\EventSubscriber\RelatedContentComputationSubscriber;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Referenced Content on person records through config-based fields.
 *
 * Fields created through the Field UI (or shipped as field.storage.* config)
 * are FieldStorageConfig, not BaseFieldDefinition. Content that names a
 * person's Other Names term only through such a field must still be listed
 * and grouped under that term's vocabulary.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2312
 */
#[CoversClass(RelatedContentComputationSubscriber::class)]
#[Group('mukurtu_person')]
class PersonReferencedContentConfigFieldTest extends PersonTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_taxonomy',
  ];

  /**
   * The term in the person's Other Names.
   */
  protected TermInterface $term;

  /**
   * A term the person does not reference.
   */
  protected TermInterface $otherTerm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Vocabulary::create(['vid' => 'contributor', 'name' => 'Contributor'])->save();
    $this->term = Term::create(['vid' => 'contributor', 'name' => 'Eunice Kitto']);
    $this->term->save();
    $this->otherTerm = Term::create(['vid' => 'contributor', 'name' => 'Someone Else']);
    $this->otherTerm->save();

    // A Field UI style term reference field on a separate content type.
    NodeType::create(['type' => 'heritage', 'name' => 'Heritage'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_contributors',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_contributors',
      'entity_type' => 'node',
      'bundle' => 'heritage',
      'label' => 'Contributors',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['contributor' => 'contributor']],
      ],
    ])->save();
  }

  /**
   * Creates a published heritage node referencing the given term.
   */
  protected function createHeritage(string $title, TermInterface $term): NodeInterface {
    $node = Node::create([
      'type' => 'heritage',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_contributors' => [['target_id' => $term->id()]],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Content naming the person in a config field is listed and grouped.
   */
  public function testConfigFieldReferenceIsReferencedContent(): void {
    $person = $this->buildPerson('Eunice Kitto');
    $person->set('field_other_names', [['target_id' => $this->term->id()]]);
    $person->save();

    $match = $this->createHeritage('Names the person', $this->term);
    $noMatch = $this->createHeritage('Names someone else', $this->otherTerm);

    $person = Node::load($person->id());
    $relatedIds = array_map('intval', array_column($person->get('field_all_related_content')->getValue(), 'target_id'));
    $this->assertContains((int) $match->id(), $relatedIds);
    $this->assertNotContains((int) $noMatch->id(), $relatedIds);

    $event = new RelatedContentProvenanceEvent($person, [
      $match->id() => $match,
      $noMatch->id() => $noMatch,
    ]);
    $this->container->get('event_dispatcher')->dispatch($event, RelatedContentProvenanceEvent::EVENT_NAME);

    $this->assertSame(['vocabularies' => ['contributor'], 'other' => FALSE], $event->provenance[$match->id()]);
    $this->assertArrayNotHasKey($noMatch->id(), $event->provenance);
  }

  /**
   * Structural node references are excluded; other node references are not.
   */
  public function testStructuralNodeReferencesAreExcluded(): void {
    foreach (['field_featured_person', ...RelatedContentComputationSubscriber::EXCLUDED_FIELDS] as $fieldName) {
      if (!FieldStorageConfig::loadByName('node', $fieldName)) {
        FieldStorageConfig::create([
          'field_name' => $fieldName,
          'entity_type' => 'node',
          'type' => 'entity_reference',
          'settings' => ['target_type' => 'node'],
        ])->save();
      }
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => 'heritage',
        'label' => $fieldName,
      ])->save();
    }

    $person = $this->buildPerson('Eunice Kitto');
    $person->set('field_other_names', [['target_id' => $this->term->id()]]);
    $person->save();

    $nodes = [];
    foreach (['field_featured_person', ...RelatedContentComputationSubscriber::EXCLUDED_FIELDS] as $fieldName) {
      $nodes[$fieldName] = Node::create([
        'type' => 'heritage',
        'title' => $fieldName,
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
        $fieldName => [['target_id' => $person->id()]],
      ]);
      $nodes[$fieldName]->save();
    }

    $person = Node::load($person->id());
    $relatedIds = array_map('intval', array_column($person->get('field_all_related_content')->getValue(), 'target_id'));
    $this->assertContains((int) $nodes['field_featured_person']->id(), $relatedIds);
    foreach (RelatedContentComputationSubscriber::EXCLUDED_FIELDS as $fieldName) {
      $this->assertNotContains((int) $nodes[$fieldName]->id(), $relatedIds, "$fieldName is not referenced content.");
    }
  }

}
