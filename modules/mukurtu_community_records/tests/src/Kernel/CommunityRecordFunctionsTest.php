<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_community_records\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests the module-level utility functions and the ValidOriginalRecord
 * constraint defined in mukurtu_community_records.
 *
 * Covers: mukurtu_community_records_has_record_field(),
 * mukurtu_community_records_entity_type_supports_records(),
 * mukurtu_community_records_is_community_record(),
 * mukurtu_community_records_is_original_record(), and the
 * ValidOriginalRecord constraint (circular reference, nested CR, target
 * is CR).
 *
 * @group mukurtu_community_records
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_community_records')]
class CommunityRecordFunctionsTest extends CommunityRecordTestBase {

  // ---------------------------------------------------------------------------
  // mukurtu_community_records_has_record_field()
  // ---------------------------------------------------------------------------

  /**
   * A node on a CR-enabled bundle reports TRUE for the original record field.
   */
  public function testHasRecordField_nodeWithField(): void {
    $node = $this->buildRecord('With Field');
    $this->assertTrue(
      mukurtu_community_records_has_record_field($node, MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)
    );
  }

  /**
   * A node on a bundle that was never given the field reports FALSE.
   */
  public function testHasRecordField_nodeWithoutField(): void {
    $node = Node::create(['type' => 'basic_page', 'title' => 'Without Field', 'uid' => $this->currentUser->id()]);
    $this->assertFalse(
      mukurtu_community_records_has_record_field($node, MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)
    );
  }

  // ---------------------------------------------------------------------------
  // mukurtu_community_records_entity_type_supports_records()
  // ---------------------------------------------------------------------------

  /**
   * The 'page' bundle (field installed) is reported as supporting CRs.
   */
  public function testEntityTypeSupportsRecords_enabled(): void {
    $this->assertTrue(mukurtu_community_records_entity_type_supports_records('node', 'page'));
  }

  /**
   * The 'basic_page' bundle (no field) is reported as not supporting CRs.
   */
  public function testEntityTypeSupportsRecords_disabled(): void {
    $this->assertFalse(mukurtu_community_records_entity_type_supports_records('node', 'basic_page'));
  }

  // ---------------------------------------------------------------------------
  // mukurtu_community_records_is_community_record()
  // ---------------------------------------------------------------------------

  /**
   * A node on a bundle without the field returns FALSE.
   */
  public function testIsCommunityRecord_noField(): void {
    $node = Node::create(['type' => 'basic_page', 'title' => 'No Field', 'uid' => $this->currentUser->id()]);
    $this->assertFalse(mukurtu_community_records_is_community_record($node));
  }

  /**
   * A node on a CR-enabled bundle with an empty original-record field returns FALSE.
   */
  public function testIsCommunityRecord_emptyField(): void {
    $node = $this->buildRecord('Empty Field');
    $this->assertFalse(mukurtu_community_records_is_community_record($node));
  }

  /**
   * A node with the original-record field set returns the original record's ID.
   */
  public function testIsCommunityRecord_withOriginalRecord(): void {
    $or = $this->buildRecord('Original Record');
    $or->save();

    $cr = $this->buildRecord('Community Record', $or);
    $cr->save();

    $this->assertEquals($or->id(), mukurtu_community_records_is_community_record($cr));
  }

  // ---------------------------------------------------------------------------
  // mukurtu_community_records_is_original_record()
  // ---------------------------------------------------------------------------

  /**
   * A node with no community records pointing to it returns FALSE.
   */
  public function testIsOriginalRecord_noCRs(): void {
    $node = $this->buildRecord('Standalone');
    $node->save();

    $this->assertFalse(mukurtu_community_records_is_original_record($node));
  }

  /**
   * A node with one community record returns an array containing that CR's ID.
   */
  public function testIsOriginalRecord_withSingleCR(): void {
    $or = $this->buildRecord('Original Record');
    $or->save();

    $cr = $this->buildRecord('Community Record', $or);
    $cr->save();

    $results = mukurtu_community_records_is_original_record($or);
    $this->assertIsArray($results);
    $this->assertContains($cr->id(), $results);
  }

  /**
   * A node with two community records returns both CR IDs.
   */
  public function testIsOriginalRecord_withMultipleCRs(): void {
    $or = $this->buildRecord('Original Record');
    $or->save();

    $cr1 = $this->buildRecord('CR 1', $or);
    $cr1->save();
    $cr2 = $this->buildRecord('CR 2', $or);
    $cr2->save();

    $results = mukurtu_community_records_is_original_record($or);
    $this->assertIsArray($results);
    $this->assertCount(2, $results);
    $this->assertContains($cr1->id(), $results);
    $this->assertContains($cr2->id(), $results);
  }

  /**
   * A non-node entity returns FALSE (function is node-only).
   */
  public function testIsOriginalRecord_nonNodeEntity(): void {
    // user is not a node; the function short-circuits with FALSE.
    $this->assertFalse(mukurtu_community_records_is_original_record($this->currentUser));
  }

  // ---------------------------------------------------------------------------
  // ValidOriginalRecord constraint
  // ---------------------------------------------------------------------------

  /**
   * A saved node cannot reference itself as its own original record.
   */
  public function testConstraint_circularReference(): void {
    $node = $this->buildRecord('Self Reference');
    $node->save();

    // Point the node at itself.
    $node->set('field_mukurtu_original_record', $node->id());
    $violations = $node->validate();

    $this->assertCount(1, $violations);
    $this->assertEquals(
      'An item %title (%id) cannot be its own community record.',
      $violations[0]->getMessageTemplate()
    );
  }

  /**
   * A node cannot designate a community record as its original record
   * (prevents nesting beyond depth 1).
   */
  public function testConstraint_targetIsCommunityRecord(): void {
    $or = $this->buildRecord('Original Record');
    $or->save();

    $cr = $this->buildRecord('Community Record', $or);
    $cr->save();

    // Try to make a new node point at the CR (which is itself a CR).
    $other = $this->buildRecord('Other');
    $other->save();
    $other->set('field_mukurtu_original_record', $cr->id());
    $violations = $other->validate();

    $this->assertCount(1, $violations);
    $this->assertEquals(
      'The item ID %id is not a valid original record target.',
      $violations[0]->getMessageTemplate()
    );
  }

  /**
   * An entity that already has community records pointing to it cannot itself
   * become a community record (prevents nesting from the other direction).
   */
  public function testConstraint_entityAlreadyHasCommunityRecords(): void {
    $or = $this->buildRecord('Original Record');
    $or->save();

    $cr = $this->buildRecord('Community Record', $or);
    $cr->save();

    // Build an independent node to serve as a valid reference target.
    $other = $this->buildRecord('Other Target');
    $other->save();

    // OR already has CRs pointing to it; trying to make OR itself a CR
    // should fail.
    $or->set('field_mukurtu_original_record', $other->id());
    $violations = $or->validate();

    $this->assertCount(1, $violations);
    $this->assertEquals(
      'The item %title (%id) cannot be a community record.',
      $violations[0]->getMessageTemplate()
    );
  }

  /**
   * A saved node pointing at a valid (non-CR, non-self) target passes
   * validation with no violations.
   */
  public function testConstraint_validOriginalRecord(): void {
    $or = $this->buildRecord('Original Record');
    $or->save();

    $cr = $this->buildRecord('Community Record');
    $cr->save();

    // Set a valid original record on an already-saved (non-new) entity so
    // the URL access check inside the constraint is skipped.
    $cr->set('field_mukurtu_original_record', $or->id());
    $violations = $cr->validate();

    $this->assertEquals(0, $violations->count());
  }

}
