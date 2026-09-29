<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\mukurtu_core\Form\AjaxRowTableAccessibilityTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shared AJAX-repeatable-row accessibility scaffolding.
 *
 * Pure array building with no Drupal service dependencies, so this is a
 * unit test with no Drupal bootstrap. Used by MukurtuImportStrategyForm,
 * CollectionOrganizationForm, and MukurtuContentWarningsSettingsForm (see
 * issues #1976, #1978, #1979) so it's a single shared point of failure for
 * three forms' accessibility behavior.
 */
#[Group('mukurtu_core')]
class AjaxRowTableAccessibilityTraitTest extends UnitTestCase {

  /**
   * The trait under test, exposed via a minimal concrete class.
   */
  private object $subject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->subject = new class {
      use AjaxRowTableAccessibilityTrait;

      public function buildRegion(string $html_id): array {
        return $this->buildAjaxRowTableStatusRegion($html_id);
      }

      public function mark(array &$element, $message, string $status_region_id, ?string $focus_id = NULL): void {
        $this->markAjaxRowTableChanged($element, $message, $status_region_id, $focus_id);
      }

    };
  }

  /**
   * The live region carries aria-live, aria-atomic, and is visually hidden.
   */
  public function testStatusRegionMarkup(): void {
    $region = $this->subject->buildRegion('example-status');

    $this->assertSame('html_tag', $region['#type']);
    $this->assertSame('div', $region['#tag']);
    $this->assertSame('example-status', $region['#attributes']['id']);
    $this->assertSame('polite', $region['#attributes']['aria-live']);
    $this->assertSame('true', $region['#attributes']['aria-atomic']);
    $this->assertContains('visually-hidden', $region['#attributes']['class']);
  }

  /**
   * Marking an element sets the marker plus status/focus data attributes.
   */
  public function testMarkAjaxRowTableChangedWithFocusTarget(): void {
    $element = ['#attributes' => ['class' => ['existing-class']]];
    $this->subject->mark($element, 'Table updated.', 'example-status', 'example-add-button');

    $this->assertSame('true', $element['#attributes']['data-mukurtu-ajax-row-table-changed']);
    $this->assertSame('Table updated.', $element['#attributes']['data-mukurtu-status-message']);
    $this->assertSame('example-status', $element['#attributes']['data-mukurtu-status-target']);
    $this->assertSame('example-add-button', $element['#attributes']['data-mukurtu-focus-target']);
    // Pre-existing attributes on the element are untouched.
    $this->assertSame(['existing-class'], $element['#attributes']['class']);
  }

  /**
   * The focus-target attribute is omitted entirely when no focus id is given.
   */
  public function testMarkAjaxRowTableChangedWithoutFocusTarget(): void {
    $element = [];
    $this->subject->mark($element, 'Table updated.', 'example-status');

    $this->assertArrayNotHasKey('data-mukurtu-focus-target', $element['#attributes']);
  }

}
