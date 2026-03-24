<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_place\Kernel;

use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Drupal\mukurtu_place\Entity\Place;
use Drupal\mukurtu_place\PlaceInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\Entity\Node;

/**
 * Tests the Place entity bundle class, interfaces, and field definitions.
 *
 * Covers: bundle class assignment via hook_entity_bundle_info_alter, interface
 * implementation, required vs optional fields, field cardinality, protocol
 * field persistence, and draft field persistence.
 *
 * @group mukurtu_place
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_place')]
class PlaceEntityTest extends PlaceTestBase {

  // ---------------------------------------------------------------------------
  // Bundle class and interfaces
  // ---------------------------------------------------------------------------

  /**
   * Loading a place node returns the Place bundle class and required
   * interfaces.
   */
  public function testPlaceBundleClassAndInterfaces(): void {
    $place = $this->buildPlace('Test Place');
    $place->save();

    $loaded = Node::load($place->id());

    $this->assertInstanceOf(Place::class, $loaded);
    $this->assertInstanceOf(PlaceInterface::class, $loaded);
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
    $place = $this->buildPlace('Keywords Test');
    $def = $place->getFieldDefinition('field_keywords');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_place_type is multi-valued (cardinality -1) and targets place_type vocabulary.
   */
  public function testPlaceTypeFieldIsMultiValuedAndTargetsPlaceType(): void {
    $place = $this->buildPlace('Place Type Test');
    $def = $place->getFieldDefinition('field_place_type');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $handlerSettings = $def->getSetting('handler_settings');
    $this->assertArrayHasKey('place_type', $handlerSettings['target_bundles']);
  }

  /**
   * field_media_assets is multi-valued (cardinality -1).
   */
  public function testMediaAssetsFieldIsMultiValued(): void {
    $place = $this->buildPlace('Media Test');
    $def = $place->getFieldDefinition('field_media_assets');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_sections is multi-valued (cardinality -1) and targets paragraph.
   */
  public function testSectionsFieldIsMultiValuedParagraph(): void {
    $place = $this->buildPlace('Sections Test');
    $def = $place->getFieldDefinition('field_sections');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('paragraph', $def->getSetting('target_type'));
  }

  /**
   * field_related_content is multi-valued and targets node.
   */
  public function testRelatedContentFieldIsMultiValuedNode(): void {
    $place = $this->buildPlace('Related Content Test');
    $def = $place->getFieldDefinition('field_related_content');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('node', $def->getSetting('target_type'));
  }

  /**
   * field_other_place_names is multi-valued and targets taxonomy_term.
   */
  public function testOtherPlaceNamesFieldIsMultiValuedTaxonomy(): void {
    $place = $this->buildPlace('Other Names Test');
    $def = $place->getFieldDefinition('field_other_place_names');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals('taxonomy_term', $def->getSetting('target_type'));
  }

  /**
   * field_coverage has cardinality 1.
   */
  public function testCoverageFieldIsSingleValued(): void {
    $place = $this->buildPlace('Coverage Test');
    $def = $place->getFieldDefinition('field_coverage');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_coverage_description has cardinality 1.
   */
  public function testCoverageDescriptionFieldIsSingleValued(): void {
    $place = $this->buildPlace('Coverage Desc Test');
    $def = $place->getFieldDefinition('field_coverage_description');
    $this->assertNotNull($def);
    $this->assertEquals(1, $def->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * field_location is multi-valued and targets location vocabulary.
   */
  public function testLocationFieldIsMultiValuedAndTargetsLocation(): void {
    $place = $this->buildPlace('Location Test');
    $def = $place->getFieldDefinition('field_location');
    $this->assertNotNull($def);
    $this->assertEquals(-1, $def->getFieldStorageDefinition()->getCardinality());
    $handlerSettings = $def->getSetting('handler_settings');
    $this->assertArrayHasKey('location', $handlerSettings['target_bundles']);
  }

  /**
   * Place does not have a field_date_born or field_date_died (unlike Person).
   */
  public function testPlaceHasNoBornDiedFields(): void {
    $place = $this->buildPlace('No Date Fields Test');
    $this->assertNull($place->getFieldDefinition('field_date_born'));
    $this->assertNull($place->getFieldDefinition('field_date_died'));
  }

  // ---------------------------------------------------------------------------
  // Field required status
  // ---------------------------------------------------------------------------

  /**
   * All place bundle fields are optional (none are required).
   */
  public function testAllBundleFieldsAreOptional(): void {
    $place = $this->buildPlace('Optional Fields Test');
    $optional_fields = [
      'field_keywords',
      'field_place_type',
      'field_media_assets',
      'field_sections',
      'field_related_content',
      'field_other_place_names',
      'field_coverage',
      'field_coverage_description',
      'field_location',
      'field_local_contexts_projects',
      'field_local_contexts_labels_and_notices',
    ];
    foreach ($optional_fields as $field_name) {
      $def = $place->getFieldDefinition($field_name);
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
    $place = $this->buildPlace('Protocol Test');
    $place->save();

    $loaded = Node::load($place->id());

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
    $place = $this->buildPlace('Draft Test');
    $place->setDraft();
    $place->save();

    $loaded = Node::load($place->id());
    $this->assertTrue($loaded->isDraft());
  }

  /**
   * A newly created place is not a draft by default.
   */
  public function testPlaceIsNotDraftByDefault(): void {
    $place = $this->buildPlace('Non-Draft Test');
    $this->assertFalse($place->isDraft());
  }

}
