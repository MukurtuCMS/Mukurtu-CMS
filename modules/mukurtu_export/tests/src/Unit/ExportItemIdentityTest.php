<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Unit;

use Drupal\mukurtu_export\ExportItemIdentity;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\mukurtu_export\ExportItemIdentity
 * @group mukurtu_export
 */
class ExportItemIdentityTest extends UnitTestCase {

  /**
   * @covers ::encode
   */
  public function testEncodeWithoutLangcodeReturnsBareId(): void {
    $this->assertSame('42', ExportItemIdentity::encode(42));
    $this->assertSame('42', ExportItemIdentity::encode(42, NULL));
    $this->assertSame('42', ExportItemIdentity::encode(42, ''));
  }

  /**
   * @covers ::encode
   */
  public function testEncodeWithLangcodeReturnsCompositeKey(): void {
    $this->assertSame('42:es', ExportItemIdentity::encode(42, 'es'));
  }

  /**
   * @covers ::decode
   */
  public function testDecodeBareIdHasNullLangcode(): void {
    [$id, $langcode] = ExportItemIdentity::decode('42');
    $this->assertSame('42', $id);
    $this->assertNull($langcode);
  }

  /**
   * @covers ::decode
   */
  public function testDecodeCompositeKeySplitsIdAndLangcode(): void {
    [$id, $langcode] = ExportItemIdentity::decode('42:es');
    $this->assertSame('42', $id);
    $this->assertSame('es', $langcode);
  }

  /**
   * encode()/decode() round-trip for both shapes.
   *
   * @covers ::encode
   * @covers ::decode
   */
  public function testRoundTrip(): void {
    $this->assertSame(['42', NULL], ExportItemIdentity::decode(ExportItemIdentity::encode(42)));
    $this->assertSame(['42', 'es'], ExportItemIdentity::decode(ExportItemIdentity::encode(42, 'es')));
  }

}
