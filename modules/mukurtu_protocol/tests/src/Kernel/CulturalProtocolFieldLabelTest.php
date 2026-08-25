<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_protocol\Entity\MukurtuNode;

/**
 * Tests that the "Cultural Protocols" field label is translatable.
 *
 * @see \Drupal\mukurtu_protocol\CulturalProtocolControlledTrait::getProtocolFieldDefinitions()
 * @group mukurtu_protocol
 */
class CulturalProtocolFieldLabelTest extends ProtocolAwareEntityTestBase {

  /**
   * The label was a plain string, meaning it could never be interface-
   * translated. It's consumed by ~15 entity classes across 7+ modules
   * (mukurtu_protocol, mukurtu_collection, mukurtu_dictionary, mukurtu_media,
   * mukurtu_place, mukurtu_digital_heritage, mukurtu_person) via this one
   * shared trait method.
   */
  public function testCulturalProtocolsFieldLabelIsTranslatable(): void {
    $definitions = MukurtuNode::getProtocolFieldDefinitions();
    $label = $definitions['field_cultural_protocols']->getLabel();

    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('Cultural Protocols', (string) $label);
  }

}
