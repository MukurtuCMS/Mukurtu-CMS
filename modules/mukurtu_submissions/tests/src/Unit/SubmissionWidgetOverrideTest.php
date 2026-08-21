<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Unit;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_submissions\Form\SubmissionSettingsForm;

/**
 * Tests that the public submission form always gets an accessible widget
 * for geofield-type fields, regardless of what the bundle's own "default"
 * form display uses.
 *
 * @see \Drupal\mukurtu_submissions\Form\SubmissionSettingsForm::applySubmissionWidgetOverride()
 * @group mukurtu_submissions
 */
class SubmissionWidgetOverrideTest extends UnitTestCase {

  /**
   * Builds a SubmissionSettingsForm with mocked dependencies and invokes
   * the protected applySubmissionWidgetOverride() method on it.
   */
  protected function applyOverride(EntityFormDisplayInterface $display, string $fieldType): void {
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getType')->willReturn($fieldType);

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn(['field_coverage' => $fieldDefinition]);

    $form = new SubmissionSettingsForm(
      $this->createMock(EntityTypeBundleInfoInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityDisplayRepositoryInterface::class),
      $entityFieldManager,
    );

    $method = new \ReflectionMethod($form, 'applySubmissionWidgetOverride');
    $method->setAccessible(TRUE);
    $method->invoke($form, $display, 'node', 'digital_heritage', 'field_coverage');
  }

  /**
   * A geofield component (whatever widget the bundle's own display uses)
   * gets switched to the accessible widget, keeping its weight/region.
   */
  public function testGeofieldWidgetIsOverriddenOnTheSubmissionForm(): void {
    $display = $this->createMock(EntityFormDisplayInterface::class);
    $display->method('getComponent')->with('field_coverage')->willReturn([
      'type' => 'geofield_mukurtu',
      'weight' => 31,
      'region' => 'content',
      'settings' => ['map' => ['leaflet_map' => 'OSM Mapnik']],
    ]);

    $display->expects($this->once())
      ->method('setComponent')
      ->with('field_coverage', $this->callback(function (array $component): bool {
        return $component['type'] === 'geofield_mukurtu_latlon'
          && $component['weight'] === 31
          && $component['region'] === 'content';
      }));

    $this->applyOverride($display, 'geofield');
  }

  /**
   * Fields of any other type are left completely alone.
   */
  public function testNonGeofieldWidgetsAreLeftAlone(): void {
    $display = $this->createMock(EntityFormDisplayInterface::class);
    $display->method('getComponent')->with('field_coverage')->willReturn([
      'type' => 'string_textfield',
      'weight' => 1,
      'region' => 'content',
      'settings' => [],
    ]);
    $display->expects($this->never())->method('setComponent');

    $this->applyOverride($display, 'string');
  }

  /**
   * A field with no component on the display (e.g. not yet included) is
   * left alone rather than getting a component created for it.
   */
  public function testFieldWithNoExistingComponentIsLeftAlone(): void {
    $display = $this->createMock(EntityFormDisplayInterface::class);
    $display->method('getComponent')->with('field_coverage')->willReturn(NULL);
    $display->expects($this->never())->method('setComponent');

    $this->applyOverride($display, 'geofield');
  }

}
