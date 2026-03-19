<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\mukurtu_digital_heritage\DigitalHeritageInterface;
use Drupal\mukurtu_digital_heritage\Entity\DigitalHeritage;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\Entity\Node;

/**
 * Tests the DigitalHeritage entity class, interfaces, and field definitions.
 *
 * @group mukurtu_digital_heritage
 */
class DigitalHeritageEntityTest extends DigitalHeritageTestBase {

  /**
   * Test that loading a digital heritage node returns the custom entity class
   * and that it implements all required interfaces.
   */
  public function testEntityClassAndInterfaces(): void {
    $category = $this->createCategory('History');
    $item = $this->buildDigitalHeritage('Interface Test', [$category]);
    $item->save();

    $loaded = Node::load($item->id());

    $this->assertInstanceOf(DigitalHeritage::class, $loaded);
    $this->assertInstanceOf(DigitalHeritageInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuDraftInterface::class, $loaded);
  }

  /**
   * Test that field_category is required and field_description is not.
   */
  public function testRequiredFieldDefinitions(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'digital_heritage');

    $this->assertTrue($definitions['title']->isRequired());
    $this->assertTrue($definitions['field_category']->isRequired());

    $this->assertFalse($definitions['field_description']->isRequired());
    $this->assertFalse($definitions['field_cultural_narrative']->isRequired());
    $this->assertFalse($definitions['field_keywords']->isRequired());
    $this->assertFalse($definitions['field_creator']->isRequired());
    $this->assertFalse($definitions['field_source']->isRequired());
  }

  /**
   * Test that multi-value fields have cardinality -1 and single-value fields
   * have cardinality 1.
   */
  public function testFieldCardinality(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'digital_heritage');

    // Multi-value fields.
    $this->assertEquals(-1, $definitions['field_category']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_keywords']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_creator']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_contributor']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_people']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_related_content']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_media_assets']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_external_links']->getFieldStorageDefinition()->getCardinality());

    // Single-value fields.
    $this->assertEquals(1, $definitions['field_description']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_cultural_narrative']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_original_date']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_creative_commons']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_rights_statements']->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * Test auto_create settings: field_category must NOT auto-create (terms
   * must be managed by a Mukurtu Manager), while descriptor fields like
   * keywords and creator SHOULD auto-create.
   */
  public function testAutoCreateSettings(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'digital_heritage');

    $this->assertFalse(
      $definitions['field_category']->getSetting('handler_settings')['auto_create'],
      'field_category must not auto-create — categories are manager-controlled.'
    );

    foreach (['field_keywords', 'field_creator', 'field_contributor', 'field_people', 'field_subject', 'field_language', 'field_format', 'field_type', 'field_publisher'] as $field_name) {
      $this->assertTrue(
        $definitions[$field_name]->getSetting('handler_settings')['auto_create'],
        "$field_name should auto-create taxonomy terms."
      );
    }
  }

  /**
   * Test that the protocol field is present and the sharing setting is stored
   * correctly on a saved digital heritage item.
   */
  public function testProtocolFieldPersistence(): void {
    $category = $this->createCategory('Art');

    $item = $this->buildDigitalHeritage('Protocol Test', [$category]);
    $item->setSharingSetting('all');
    $item->setProtocols([$this->protocol]);
    $item->save();

    $loaded = Node::load($item->id());
    $this->assertEquals('all', $loaded->getSharingSetting());
    $this->assertContains((int) $this->protocol->id(), $loaded->getProtocols());
  }

  /**
   * Test that validation reports a violation when field_category is empty.
   */
  public function testCategoryRequiredValidation(): void {
    $item = Node::create([
      'title' => 'Missing Category',
      'type' => 'digital_heritage',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $item->setSharingSetting('any');
    $item->setProtocols([$this->protocol]);

    $violations = $item->validate();

    $violationFields = [];
    foreach ($violations as $violation) {
      $violationFields[] = $violation->getPropertyPath();
    }
    $this->assertContains('field_category', $violationFields,
      'Validation should report a violation for missing field_category.'
    );
  }

}
