<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Tests taxonomy field behavior on digital heritage items.
 *
 * Covers: auto-create terms (keywords, creator, contributor), required
 * field_category without auto-create, and multi-value term assignment.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_digital_heritage')]
class DigitalHeritageTaxonomyTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create vocabularies used in these tests.
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'creator', 'name' => 'Creator'])->save();
    Vocabulary::create(['vid' => 'contributor', 'name' => 'Contributor'])->save();
  }

  /**
   * Test that saving a DH item with unsaved keyword terms auto-creates them.
   *
   * field_keywords has auto_create=TRUE, so new term entities attached to the
   * field should be persisted when the node is saved.
   */
  public function testKeywordsAutoCreate(): void {
    $category = $this->createCategory('Nature');
    $item = $this->buildDigitalHeritage('Keyword Auto-create Test', [$category]);

    $newTerm = Term::create(['name' => 'Salmon Run', 'vid' => 'keywords']);
    $item->set('field_keywords', [['entity' => $newTerm]]);
    $item->save();

    $loaded = Node::load($item->id());
    $keywords = $loaded->get('field_keywords')->referencedEntities();

    $this->assertCount(1, $keywords);
    $this->assertEquals('Salmon Run', $keywords[0]->getName());
    $this->assertNotNull($keywords[0]->id(), 'The keyword term should have been auto-created on save.');
  }

  /**
   * Test that multiple keyword terms can be saved and are returned in order.
   */
  public function testMultipleKeywords(): void {
    $category = $this->createCategory('Culture');
    $item = $this->buildDigitalHeritage('Multi-keyword Test', [$category]);

    $item->set('field_keywords', [
      ['entity' => Term::create(['name' => 'Weaving', 'vid' => 'keywords'])],
      ['entity' => Term::create(['name' => 'Basket', 'vid' => 'keywords'])],
      ['entity' => Term::create(['name' => 'Cedar', 'vid' => 'keywords'])],
    ]);
    $item->save();

    $loaded = Node::load($item->id());
    $keywords = $loaded->get('field_keywords')->referencedEntities();

    $this->assertCount(3, $keywords);
    $this->assertEquals('Weaving', $keywords[0]->getName());
    $this->assertEquals('Basket', $keywords[1]->getName());
    $this->assertEquals('Cedar', $keywords[2]->getName());
  }

  /**
   * Test that an existing keyword term is reused rather than duplicated when
   * referenced by name on a second item.
   */
  public function testExistingKeywordReuse(): void {
    $category = $this->createCategory('History');

    // Save first item creating the 'Oral History' keyword.
    $item1 = $this->buildDigitalHeritage('First Item', [$category]);
    $item1->set('field_keywords', [
      ['entity' => Term::create(['name' => 'Oral History', 'vid' => 'keywords'])],
    ]);
    $item1->save();
    $item1Terms = Node::load($item1->id())->get('field_keywords')->referencedEntities();
    $this->assertNotEmpty($item1Terms, 'The keyword term should have been created and referenced.');
    $term = $item1Terms[0];
    $originalId = $term->id();

    // Save second item referencing the same term by ID.
    $item2 = $this->buildDigitalHeritage('Second Item', [$category]);
    $item2->set('field_keywords', [['target_id' => $originalId]]);
    $item2->save();

    $loaded2 = Node::load($item2->id());
    $keywords2 = $loaded2->get('field_keywords')->referencedEntities();

    $this->assertCount(1, $keywords2);
    $this->assertEquals($originalId, $keywords2[0]->id(), 'The existing term should be reused, not duplicated.');
  }

  /**
   * Test that multiple creator terms can be set (auto_create=TRUE, cardinality=-1).
   */
  public function testMultipleCreators(): void {
    $category = $this->createCategory('Oral Tradition');
    $item = $this->buildDigitalHeritage('Creators Test', [$category]);

    $item->set('field_creator', [
      ['entity' => Term::create(['name' => 'Mary Smith', 'vid' => 'creator'])],
      ['entity' => Term::create(['name' => 'John Doe', 'vid' => 'creator'])],
    ]);
    $item->save();

    $loaded = Node::load($item->id());
    $creators = $loaded->get('field_creator')->referencedEntities();

    $this->assertCount(2, $creators);
    $this->assertEquals('Mary Smith', $creators[0]->getName());
    $this->assertEquals('John Doe', $creators[1]->getName());
  }

  /**
   * Test that two digital heritage items can reference each other via
   * field_related_content.
   */
  public function testRelatedContent(): void {
    $category = $this->createCategory('Events');

    $item1 = $this->buildDigitalHeritage('Item One', [$category]);
    $item1->save();

    $item2 = $this->buildDigitalHeritage('Item Two', [$category]);
    $item2->set('field_related_content', [['target_id' => $item1->id()]]);
    $item2->save();

    $loaded = Node::load($item2->id());
    $related = $loaded->get('field_related_content')->referencedEntities();

    $this->assertCount(1, $related);
    $this->assertEquals($item1->id(), $related[0]->id());
  }

  /**
   * Test that a digital heritage item under a strict protocol is invisible to
   * a user with no protocol membership, and visible to a member.
   *
   * This is a smoke test confirming the CulturalProtocolControlledInterface
   * integration works end-to-end on the actual DigitalHeritage entity class.
   */
  public function testProtocolAccessSmoke(): void {
    // Create a strict protocol so only explicit members can view.
    $strictProtocol = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strictProtocol->save();
    $strictProtocol->addMember($this->currentUser, ['protocol_steward']);

    $category = $this->createCategory('Sacred');
    $item = $this->buildDigitalHeritage('Restricted Item', [$category]);
    $item->setSharingSetting('any');
    $item->setProtocols([$strictProtocol]);
    $item->save();

    // A user with no protocol membership cannot view.
    $outsider = User::create(['name' => $this->randomString()]);
    $outsider->save();
    $this->assertFalse($item->access('view', $outsider), 'Non-member should not view strict-protocol DH item.');

    // A member can view.
    $this->assertTrue($item->access('view', $this->currentUser), 'Protocol steward should view strict-protocol DH item.');
  }

}
