<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests RoundtripEntityTypeRegistry discovers custom entity types by interface.
 *
 * @see \Drupal\mukurtu_core\Service\RoundtripEntityTypeRegistry
 * @see \Drupal\mukurtu_core\Entity\RoundtripEntityInterface
 */
#[Group('mukurtu_core')]
class RoundtripEntityTypeRegistryTest extends ProtocolAwareEntityTestBase {

  /**
   * The Community and Protocol entity classes implement the marker
   * interface, so both should be discovered automatically without any
   * mukurtu_import/mukurtu_export code referencing them by name.
   */
  public function testDiscoversEntityTypesImplementingTheInterface(): void {
    $registry = \Drupal::service('mukurtu_core.roundtrip_entity_types');
    $entity_type_ids = $registry->getCustomEntityTypeIds();

    $this->assertContains('community', $entity_type_ids);
    $this->assertContains('protocol', $entity_type_ids);
  }

  /**
   * Entity types that don't implement the interface (e.g. user) are excluded.
   */
  public function testExcludesEntityTypesNotImplementingTheInterface(): void {
    $registry = \Drupal::service('mukurtu_core.roundtrip_entity_types');
    $entity_type_ids = $registry->getCustomEntityTypeIds();

    $this->assertNotContains('user', $entity_type_ids);
    $this->assertNotContains('node', $entity_type_ids);
  }

}
