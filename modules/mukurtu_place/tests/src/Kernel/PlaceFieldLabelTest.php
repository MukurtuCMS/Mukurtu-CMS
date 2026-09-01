<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_place\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_place\Entity\Place;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Place's "Location Description" field label is translatable.
 */
#[Group('mukurtu_place')]
class PlaceFieldLabelTest extends ProtocolAwareEntityTestBase {

  public function testCoverageDescriptionFieldLabelIsTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('node');
    $definitions = Place::bundleFieldDefinitions($entityType, '', []);

    $label = $definitions['field_coverage_description']->getLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('Location Description', (string) $label);
  }

}
