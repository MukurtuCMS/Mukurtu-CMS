<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Unit;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_submissions\SubmissionFormDisplayManager;

/**
 * Tests that the public submission form always gets an accessible widget
 * for geofield-type fields, regardless of what the bundle's own "default"
 * form display uses.
 *
 * @see \Drupal\mukurtu_submissions\SubmissionFormDisplayManager::applySubmissionWidgetOverride()
 * @group mukurtu_submissions
 */
class SubmissionWidgetOverrideTest extends UnitTestCase {

  /**
   * Builds a SubmissionFormDisplayManager with mocked dependencies and
   * invokes applySubmissionWidgetOverride() on it.
   */
  protected function applyOverride(array $component, string $fieldType): array {
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getType')->willReturn($fieldType);

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn(['field_coverage' => $fieldDefinition]);

    $manager = new SubmissionFormDisplayManager(
      $this->createMock(EntityDisplayRepositoryInterface::class),
      $entityFieldManager,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityTypeBundleInfoInterface::class),
    );

    return $manager->applySubmissionWidgetOverride('field_coverage', $component, 'node', 'digital_heritage');
  }

  /**
   * A geofield component (whatever widget the bundle's own display uses)
   * gets switched to the accessible widget, keeping its weight/region.
   */
  public function testGeofieldWidgetIsOverriddenOnTheSubmissionForm(): void {
    $component = $this->applyOverride([
      'type' => 'geofield_mukurtu',
      'weight' => 31,
      'region' => 'content',
      'settings' => ['map' => ['leaflet_map' => 'OSM Mapnik']],
    ], 'geofield');

    $this->assertSame('geofield_mukurtu_latlon', $component['type']);
    $this->assertSame(31, $component['weight']);
    $this->assertSame('content', $component['region']);
  }

  /**
   * Fields of any other type are left completely alone.
   */
  public function testNonGeofieldWidgetsAreLeftAlone(): void {
    $original = [
      'type' => 'string_textfield',
      'weight' => 1,
      'region' => 'content',
      'settings' => [],
    ];

    $this->assertSame($original, $this->applyOverride($original, 'string'));
  }

  /**
   * A field with no existing component (e.g. not yet included) is left
   * alone rather than getting a component created for it.
   */
  public function testFieldWithNoExistingComponentIsLeftAlone(): void {
    $this->assertSame([], $this->applyOverride([], 'geofield'));
  }

}
