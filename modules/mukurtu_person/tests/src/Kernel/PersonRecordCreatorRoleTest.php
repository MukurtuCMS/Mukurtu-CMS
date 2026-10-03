<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\mukurtu_core\Event\RelatedContentProvenanceEvent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a creator with a role still counts toward a person record.
 */
#[Group('mukurtu_person')]
class PersonRecordCreatorRoleTest extends PersonTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'flat_taxonomy',
    'mukurtu_digital_heritage',
    'mukurtu_search',
    'search_api',
    'mukurtu_taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital Heritage'])->save();
    foreach (['category', 'creator', 'role'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
  }

  /**
   * An item naming the person as a creator, with a role, is referenced.
   */
  public function testCreatorWithRoleAppearsInReferencedContent(): void {
    $name = Term::create(['vid' => 'creator', 'name' => 'Eunice Kitto']);
    $name->save();
    $role = Term::create(['vid' => 'role', 'name' => 'Singer']);
    $role->save();
    $category = Term::create(['vid' => 'category', 'name' => 'Songs']);
    $category->save();

    $item = Node::create([
      'type' => 'digital_heritage',
      'title' => 'Recording',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_category' => [$category],
      'field_creator' => [['target_id' => $name->id(), 'role_target_id' => $role->id()]],
    ]);
    $item->setSharingSetting('any');
    $item->setProtocols([$this->protocol]);
    $item->save();

    $person = $this->buildPerson('Eunice Kitto');
    $person->set('field_other_names', [$name]);
    $person->save();

    $related = array_column($person->get('field_all_related_content')->getValue(), 'target_id');
    $this->assertContains($item->id(), $related);

    // The vocabulary filter groups it under Creator, not "other".
    $event = new RelatedContentProvenanceEvent($person, [$item->id() => $item]);
    $this->container->get('event_dispatcher')->dispatch($event, RelatedContentProvenanceEvent::EVENT_NAME);
    $this->assertSame(['creator'], $event->provenance[$item->id()]['vocabularies']);
  }

}
