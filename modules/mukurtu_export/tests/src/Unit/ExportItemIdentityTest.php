<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_export\ExportItemIdentity;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ExportItemIdentity.
 */
#[CoversMethod(ExportItemIdentity::class, 'encode')]
#[CoversMethod(ExportItemIdentity::class, 'decode')]
#[Group('mukurtu_export')]
class ExportItemIdentityTest extends UnitTestCase {

  /**
   * Tests encode() without a langcode returns the bare id.
   */
  public function testEncodeWithoutLangcodeReturnsBareId(): void {
    $this->assertSame('42', ExportItemIdentity::encode(42));
    $this->assertSame('42', ExportItemIdentity::encode(42, NULL));
    $this->assertSame('42', ExportItemIdentity::encode(42, ''));
  }

  /**
   * Tests encode() with a langcode returns a composite key.
   */
  public function testEncodeWithLangcodeReturnsCompositeKey(): void {
    $this->assertSame('42:es', ExportItemIdentity::encode(42, 'es'));
  }

  /**
   * Tests decode() of a bare id has a null langcode.
   */
  public function testDecodeBareIdHasNullLangcode(): void {
    [$id, $langcode] = ExportItemIdentity::decode('42');
    $this->assertSame('42', $id);
    $this->assertNull($langcode);
  }

  /**
   * Tests decode() of a composite key splits id and langcode.
   */
  public function testDecodeCompositeKeySplitsIdAndLangcode(): void {
    [$id, $langcode] = ExportItemIdentity::decode('42:es');
    $this->assertSame('42', $id);
    $this->assertSame('es', $langcode);
  }

  /**
   * encode()/decode() round-trip for both shapes.
   */
  public function testRoundTrip(): void {
    $this->assertSame(['42', NULL], ExportItemIdentity::decode(ExportItemIdentity::encode(42)));
    $this->assertSame(['42', 'es'], ExportItemIdentity::decode(ExportItemIdentity::encode(42, 'es')));
  }

}
