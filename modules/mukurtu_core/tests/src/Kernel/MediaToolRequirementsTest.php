<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\MediaToolRequirements;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the status report requirement for media processing tools.
 *
 * The real check shells out, so which binaries exist depends on the machine.
 * These tests override the probe instead of asserting against whatever the
 * test container happens to have installed, which would make the suite pass or
 * fail for reasons unrelated to the code.
 *
 * @see \Drupal\mukurtu_core\Hook\MediaToolRequirements
 */
#[Group('mukurtu_core')]
class MediaToolRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  protected const KEY = 'mukurtu_core_media_tools';

  /**
   * Returns a hook whose installed-check is driven by the given list.
   *
   * @param string[] $present
   *   Binaries to report as installed.
   */
  protected function hookReporting(array $present): MediaToolRequirements {
    return new class($present) extends MediaToolRequirements {

      public function __construct(protected array $present) {}

      protected function isInstalled(string $binary, string $probe): bool {
        return in_array($binary, $this->present, TRUE);
      }

      protected function execIsDisabled(): bool {
        return FALSE;
      }

    };
  }

  /**
   * All three tools present reports OK with no description.
   */
  public function testOkWhenAllToolsPresent(): void {
    $r = $this->hookReporting(['pdftotext', 'pdftoppm', 'ffmpeg'])->runtimeRequirements();

    $this->assertSame(RequirementSeverity::OK, $r[static::KEY]['severity']);
    $this->assertSame('Available', (string) $r[static::KEY]['value']);
    $this->assertArrayNotHasKey('description', $r[static::KEY]);
  }

  /**
   * A missing tool is named in the value and warned about.
   */
  public function testWarnsAndNamesMissingTool(): void {
    $r = $this->hookReporting(['pdftotext', 'pdftoppm'])->runtimeRequirements();

    $this->assertSame(RequirementSeverity::Warning, $r[static::KEY]['severity']);
    $this->assertSame('ffmpeg not found', (string) $r[static::KEY]['value']);
    $this->assertArrayHasKey('description', $r[static::KEY]);
  }

  /**
   * Several missing tools are listed in the declared order.
   */
  public function testListsSeveralMissingToolsInOrder(): void {
    $r = $this->hookReporting(['pdftoppm'])->runtimeRequirements();

    $this->assertSame('pdftotext, ffmpeg not found', (string) $r[static::KEY]['value']);
  }

  /**
   * With nothing installed, all three are reported.
   */
  public function testAllMissing(): void {
    $r = $this->hookReporting([])->runtimeRequirements();

    $this->assertSame(
      'pdftotext, pdftoppm, ffmpeg not found',
      (string) $r[static::KEY]['value']
    );
    $this->assertSame(RequirementSeverity::Warning, $r[static::KEY]['severity']);
  }

  /**
   * The description explains the silent-failure behaviour and names packages.
   */
  public function testDescriptionExplainsSilentFailure(): void {
    $r = $this->hookReporting([])->runtimeRequirements();
    $description = $r[static::KEY]['description'];

    $this->assertSame('item_list', $description['#theme']);
    $this->assertStringContainsString('fails silently', (string) $description['#prefix']);
    $items = implode(' ', array_map('strval', $description['#items']));
    $this->assertStringContainsString('poppler-utils', $items);
    $this->assertStringContainsString('found by search', $items);
    $this->assertStringContainsString('thumbnail', $items);
  }

  /**
   * Disabled exec() is reported rather than guessed at.
   */
  public function testReportsWhenExecIsDisabled(): void {
    $hook = new class extends MediaToolRequirements {

      protected function execIsDisabled(): bool {
        return TRUE;
      }

    };

    $r = $hook->runtimeRequirements();

    $this->assertSame('Cannot be checked', (string) $r[static::KEY]['value']);
    $this->assertSame(RequirementSeverity::Warning, $r[static::KEY]['severity']);
    $this->assertStringContainsString('exec()', (string) $r[static::KEY]['description']);
  }

}
