<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Unit;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_protocol\Plugin\Field\FieldWidget\CulturalProtocolWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the cultural protocol widget survives a submission carrying no
 * protocol selection.
 *
 * Someone who belongs to no cultural protocol gets an empty
 * protocol_selection container, and an empty container submits nothing, so
 * neither protocol_selection nor sharing_setting reaches massageFormValues().
 * Reading them unguarded raised "Undefined array key" warnings that Drupal
 * printed to the page, leaking an internal file path and burying the real
 * message, which is that the person has no protocol to publish into. Every
 * account is in that state on a site before its first community exists.
 *
 * phpunit.xml sets failOnWarning, so the warnings alone would fail these
 * tests; the assertions additionally pin what the method should return.
 *
 * A unit test rather than a kernel one: the method is array handling, and
 * the only thing it calls out to, CulturalProtocolItem::formatProtocols(),
 * is a pure static. Booting a kernel would drag in Organic Groups for no
 * benefit.
 */
#[Group('mukurtu_protocol')]
#[CoversClass(CulturalProtocolWidget::class)]
class CulturalProtocolWidgetMassageTest extends UnitTestCase {

  /**
   * Calls the method under test without a configured widget instance.
   */
  private function massage(array $values): array {
    $widget = (new \ReflectionClass(CulturalProtocolWidget::class))
      ->newInstanceWithoutConstructor();
    return $widget->massageFormValues($values, [], new FormState());
  }

  /**
   * Neither key present: the state of any account with no protocols.
   */
  public function testSubmissionWithNoProtocolSelection(): void {
    $result = $this->massage([0 => ['value' => []]]);

    $this->assertSame('', $result[0]['protocols'], 'No protocols are recorded.');
    $this->assertSame(
      'all',
      $result[0]['sharing_setting'],
      "An unsubmitted sharing setting falls back to the widget's own default.",
    );
  }

  /**
   * The value container itself can be absent too.
   */
  public function testSubmissionWithNoValueContainer(): void {
    $result = $this->massage([0 => []]);

    $this->assertSame('', $result[0]['protocols']);
    $this->assertSame('all', $result[0]['sharing_setting']);
  }

  /**
   * A normal submission still round-trips, so the guards changed nothing for
   * the case that already worked. Protocol ids come from the array keys, and
   * an unchecked box (value 0) is dropped.
   */
  public function testSubmissionWithSelectedProtocols(): void {
    $result = $this->massage([
      0 => [
        'value' => [
          'sharing_setting' => 'any',
          'protocol_selection' => [
            0 => ['protocols' => [7 => 7, 9 => 0, 11 => 11]],
          ],
        ],
      ],
    ]);

    $this->assertSame('|7|,|11|', $result[0]['protocols']);
    $this->assertSame('any', $result[0]['sharing_setting']);
  }

}
