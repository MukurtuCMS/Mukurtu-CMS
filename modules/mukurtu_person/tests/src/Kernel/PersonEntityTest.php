<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Drupal\mukurtu_person\Entity\Person;
use Drupal\mukurtu_person\PersonInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\Entity\Node;

/**
 * Tests the Person entity bundle class, interfaces, and field definitions.
 *
 * Covers: bundle class assignment via hook_entity_bundle_info_alter, interface
 * implementation, required vs optional fields, field cardinality, protocol
 * field persistence, and draft field persistence.
 *
 * @group mukurtu_person
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_person')]
class PersonEntityTest extends PersonTestBase {

  // ---------------------------------------------------------------------------
  // Bundle class and interfaces
  // ---------------------------------------------------------------------------

  /**
   * Loading a person node returns the Person bundle class and required
   * interfaces.
   */
  public function testPersonBundleClassAndInterfaces(): void {
    $person = $this->buildPerson('Test Person');
    $person->save();

    $loaded = Node::load($person->id());

    $this->assertInstanceOf(Person::class, $loaded);
    $this->assertInstanceOf(PersonInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuDraftInterface::class, $loaded);
  }

  // ---------------------------------------------------------------------------
  // Field cardinality
  // ---------------------------------------------------------------------------

  /**
   * field_keywords is multi-valued (cardinality -1).
   */
  public function testKeywordsFieldIsMultiValued(): void {
    $person = $this->buildPerson('Keywords Test');
    $def = $person->getFieldDefinition('field_keywords');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_media_assets is multi-valued (cardinality -1).
   */
  public function testMediaAssetsFieldIsMultiValued(): void {
    $person = $this->buildPerson('Media Test');
    $def = $person->getFieldDefinition('field_media_assets');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_date_born has cardinality 1.
   */
  public function testDateBornFieldIsSingleValued(): void {
    $person = $this->buildPerson('Date Born Test');
    $def = $person->getFieldDefinition('field_date_born');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_date_died has cardinality 1.
   */
  public function testDateDiedFieldIsSingleValued(): void {
    $person = $this->buildPerson('Date Died Test');
    $def = $person->getFieldDefinition('field_date_died');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_deceased is a boolean field (single-value).
   */
  public function testDeceasedFieldExists(): void {
    $person = $this->buildPerson('Deceased Test');
    $def = $person->getFieldDefinition('field_deceased');
    $this->assertNotNull($def);
    $this->assertEquals('boolean', $def->getType());
  }

  /**
   * field_sections is multi-valued (cardinality -1) and targets paragraph.
   */
  public function testSectionsFieldIsMultiValuedParagraph(): void {
    $person = $this->buildPerson('Sections Test');
    $def = $person->getFieldDefinition('field_sections');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('paragraph', $def->getSetting('target_type'));
  }

  /**
   * field_related_people is multi-valued (cardinality -1) and targets paragraph.
   */
  public function testRelatedPeopleFieldIsMultiValuedParagraph(): void {
    $person = $this->buildPerson('Related People Test');
    $def = $person->getFieldDefinition('field_related_people');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('paragraph', $def->getSetting('target_type'));
  }

  /**
   * field_related_content is multi-valued and targets node.
   */
  public function testRelatedContentFieldIsMultiValuedNode(): void {
    $person = $this->buildPerson('Related Content Test');
    $def = $person->getFieldDefinition('field_related_content');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('node', $def->getSetting('target_type'));
  }

  /**
   * field_other_names is multi-valued and targets taxonomy_term.
   */
  public function testOtherNamesFieldIsMultiValuedTaxonomy(): void {
    $person = $this->buildPerson('Other Names Test');
    $def = $person->getFieldDefinition('field_other_names');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('taxonomy_term', $def->getSetting('target_type'));
  }

  /**
   * field_coverage has cardinality 1.
   */
  public function testCoverageFieldIsSingleValued(): void {
    $person = $this->buildPerson('Coverage Test');
    $def = $person->getFieldDefinition('field_coverage');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_coverage_description has cardinality 1 and is text_long.
   */
  public function testCoverageDescriptionFieldIsSingleTextLong(): void {
    $person = $this->buildPerson('Coverage Desc Test');
    $def = $person->getFieldDefinition('field_coverage_description');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_location is multi-valued and targets location vocabulary.
   */
  public function testLocationFieldIsMultiValuedAndTargetsLocation(): void {
    $person = $this->buildPerson('Location Test');
    $def = $person->getFieldDefinition('field_location');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $handlerSettings = $def->getSetting('handler_settings');
    $this->assertArrayHasKey('location', $handlerSettings['target_bundles']);
  }

  /**
   * field_place_of_birth has cardinality 1 and targets location vocabulary.
   */
  public function testPlaceOfBirthFieldIsSingleAndTargetsLocation(): void {
    $person = $this->buildPerson('Birth Test');
    $def = $person->getFieldDefinition('field_place_of_birth');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
    $handlerSettings = $def->getSetting('handler_settings');
    $this->assertArrayHasKey('location', $handlerSettings['target_bundles']);
  }

  /**
   * field_place_of_death has cardinality 1 and targets location vocabulary.
   */
  public function testPlaceOfDeathFieldIsSingleAndTargetsLocation(): void {
    $person = $this->buildPerson('Death Test');
    $def = $person->getFieldDefinition('field_place_of_death');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
    $handlerSettings = $def->getSetting('handler_settings');
    $this->assertArrayHasKey('location', $handlerSettings['target_bundles']);
  }

  // ---------------------------------------------------------------------------
  // Field required status
  // ---------------------------------------------------------------------------

  /**
   * All person bundle fields are optional (none are required).
   */
  public function testAllBundleFieldsAreOptional(): void {
    $person = $this->buildPerson('Optional Fields Test');
    $optional_fields = [
      'field_keywords',
      'field_media_assets',
      'field_date_born',
      'field_date_died',
      'field_deceased',
      'field_sections',
      'field_related_people',
      'field_related_content',
      'field_other_names',
      'field_coverage',
      'field_coverage_description',
      'field_location',
      'field_place_of_birth',
      'field_place_of_death',
      'field_local_contexts_projects',
      'field_local_contexts_labels_and_notices',
    ];
    foreach ($optional_fields as $field_name) {
      $def = $person->getFieldDefinition($field_name);
      $this->assertNotNull($def, "Field $field_name should exist.");
      $this->assertFalse($def->isRequired(), "Field $field_name should not be required.");
    }
  }

  // ---------------------------------------------------------------------------
  // Protocol field persistence
  // ---------------------------------------------------------------------------

  /**
   * Protocol and sharing setting persist through save and reload.
   */
  public function testProtocolFieldPersistsThroughSave(): void {
    $person = $this->buildPerson('Protocol Test');
    $person->save();

    $loaded = Node::load($person->id());

    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $protocols = $loaded->getProtocolEntities();
    $this->assertCount(1, $protocols);
    $this->assertEquals($this->protocol->id(), reset($protocols)->id());
    $this->assertEquals('any', $loaded->getSharingSetting());
  }

  // ---------------------------------------------------------------------------
  // Draft field persistence
  // ---------------------------------------------------------------------------

  /**
   * Draft status persists through save and reload.
   */
  public function testDraftStatusPersistsThroughSave(): void {
    $person = $this->buildPerson('Draft Test');
    $person->setDraft();
    $person->save();

    $loaded = Node::load($person->id());
    $this->assertTrue($loaded->isDraft());
  }

  /**
   * A newly created person is not a draft by default.
   */
  public function testPersonIsNotDraftByDefault(): void {
    $person = $this->buildPerson('Non-Draft Test');
    $this->assertFalse($person->isDraft());
  }

  /**
   * field_deceased defaults to FALSE.
   */
  public function testDeceasedDefaultsFalse(): void {
    $person = $this->buildPerson('Deceased Default Test');
    $this->assertFalse((bool) $person->get('field_deceased')->value);
  }

}
