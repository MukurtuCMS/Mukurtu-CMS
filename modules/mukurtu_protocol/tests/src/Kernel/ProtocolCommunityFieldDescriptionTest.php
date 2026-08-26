<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;

/**
 * Tests that Protocol/Community field descriptions are translatable.
 *
 * @group mukurtu_protocol
 */
class ProtocolCommunityFieldDescriptionTest extends ProtocolAwareEntityTestBase {

  public function testProtocolAccessModeDescriptionIsTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('protocol');
    $fields = Protocol::baseFieldDefinitions($entityType);

    $this->assertInstanceOf(TranslatableMarkup::class, $fields['field_access_mode']->getDescription());
  }

  /**
   * @dataProvider communityFieldProvider
   */
  public function testCommunityFieldDescriptionIsTranslatable(string $fieldName): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('community');
    $fields = Community::baseFieldDefinitions($entityType);

    $this->assertInstanceOf(TranslatableMarkup::class, $fields[$fieldName]->getDescription());
  }

  public static function communityFieldProvider(): array {
    return [
      'field_parent_community' => ['field_parent_community'],
      'field_child_communities' => ['field_child_communities'],
      'field_membership_display' => ['field_membership_display'],
    ];
  }

}
