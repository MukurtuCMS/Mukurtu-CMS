<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_person\Entity\Person;
use Drupal\mukurtu_person\Entity\RelatedPerson;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Person/RelatedPerson field labels and descriptions are
 * translatable.
 */
#[Group('mukurtu_person')]
class PersonFieldLabelTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'paragraphs',
  ];

  public function testPersonFieldDateDiedDescriptionIsTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('node');
    $definitions = Person::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_date_died']->getDescription());
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_deceased']->getDescription());
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_coverage_description']->getLabel());
  }

  public function testRelatedPersonFieldDescriptionIsTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('paragraph');
    $definitions = RelatedPerson::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_relationship_type']->getDescription());
  }

}
