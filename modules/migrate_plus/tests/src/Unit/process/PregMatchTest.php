<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\migrate_plus\Plugin\migrate\process\PregMatch;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the preg_match process plugin.
 */
#[CoversClass(PregMatch::class)]
#[Group('migrate_plus')]
final class PregMatchTest extends MigrateProcessTestCase {

  /**
   * Test basic functionality with a simple pattern.
   */
  public function testBasicPattern(): void {
    $configuration = [
      'pattern' => '/\{([^}]*)\}/',
      'group_index' => 1,
    ];
    $value = 'This is a {test} string';
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame('test', $actual);
  }

  /**
   * Test with a pattern that extracts a URL attribute.
   */
  public function testUrlAttributePattern(): void {
    $configuration = [
      'pattern' => '/url="([^"]+)"/',
      'group_index' => 1,
    ];
    $value = '<a href="#" url="https://example.com">Link</a>';
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame('https://example.com', $actual);
  }

  /**
   * Test with a pattern that doesn't match.
   */
  public function testNoMatch(): void {
    $configuration = [
      'pattern' => '/\{([^}]*)\}/',
      'group_index' => 1,
    ];
    $value = 'This is a test string without braces';
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertNull($actual);
  }

  /**
   * Test with a NULL value.
   */
  public function testNullValue(): void {
    $configuration = [
      'pattern' => '/\{([^}]*)\}/',
      'group_index' => 1,
    ];
    $value = NULL;
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertNull($actual);
  }

  /**
   * Test without a pattern configuration.
   */
  public function testMissingPattern(): void {
    $configuration = [
      'group_index' => 1,
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("preg_match process plugin requires a 'pattern' configuration.");
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $plugin->transform('test', $this->migrateExecutable, $this->row, 'destinationProperty');
  }

  /**
   * Test with default group index.
   */
  public function testDefaultGroupIndex(): void {
    $configuration = [
      'pattern' => '/Hello (\w+)/',
    ];
    $value = 'Hello World';
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame('Hello World', $actual);
  }

  /**
   * Test with a different group index.
   */
  public function testDifferentGroupIndex(): void {
    $configuration = [
      'pattern' => '/(\w+)\s+(\w+)/',
      'group_index' => 2,
    ];
    $value = 'Hello World';
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame('World', $actual);
  }

  /**
   * Test with a group index that doesn't exist.
   */
  public function testNonExistentGroupIndex(): void {
    $configuration = [
      'pattern' => '/(\w+)\s+(\w+)/',
      'group_index' => 3,
    ];
    $value = 'Hello World';
    $plugin = new PregMatch($configuration, 'preg_match', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertNull($actual);
  }

}
