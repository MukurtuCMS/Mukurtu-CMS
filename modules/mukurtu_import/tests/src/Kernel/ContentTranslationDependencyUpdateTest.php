<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40401(), which installs content_translation.
 *
 * MukurtuImportTestBase does not enable content_translation, and
 * KernelTestBase does not enforce info.yml dependencies, so this class starts
 * in exactly the state the hook exists to repair: a site carrying a declared
 * but uninstalled dependency.
 *
 * @see mukurtu_import_update_40401()
 */
#[Group('mukurtu_import')]
class ContentTranslationDependencyUpdateTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_import.install';

    $this->assertFalse(
      \Drupal::moduleHandler()->moduleExists('content_translation'),
      'Precondition: the hook has nothing to do if the module is already installed.',
    );
  }

  /**
   * Builds a strategy mapping a langcode column, as the shipped ones do.
   */
  private function langcodeMappingStrategy(): MukurtuImportStrategy {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'langcode', 'source' => 'langcode'],
    ]);
    return $strategy;
  }

  /**
   * Reads isTranslationImport(), which is protected.
   */
  private function isTranslationImport(MukurtuImportStrategy $strategy): bool {
    $method = new \ReflectionMethod($strategy, 'isTranslationImport');
    $method->setAccessible(TRUE);
    return $method->invoke($strategy, NULL);
  }

  /**
   * The hook installs the dependency a pre-4.0.0 site never received.
   */
  public function testInstallsTheMissingDependency(): void {
    $message = mukurtu_import_update_40401();

    $this->assertTrue(
      \Drupal::moduleHandler()->moduleExists('content_translation'),
      'The site is still missing the module mukurtu_import depends on.',
    );
    $this->assertTrue(
      \Drupal::hasService('content_translation.manager'),
      'The module is installed but the service the import asks for is absent.',
    );
    $this->assertNotNull($message, 'The operator was told nothing about a module being installed.');
  }

  /**
   * Running it again is a no-op, so a re-run cannot disturb the site.
   */
  public function testDoesNothingWhenTheModuleIsAlreadyInstalled(): void {
    mukurtu_import_update_40401();

    $this->assertNull(
      mukurtu_import_update_40401(),
      'The hook reported work on a site that already had the module.',
    );
  }

  /**
   * Translation-targeting imports come back once the hook has run.
   *
   * The guard in isTranslationImport() returns FALSE while the module is
   * missing, which is what stops the crash. This pins that the guard is a
   * gate and not an off switch: once the hook installs the module and a
   * bundle is translatable, the feature engages again. Without this, a guard
   * that always returned FALSE would pass every other test here.
   */
  public function testTranslationImportsWorkAgainAfterTheHook(): void {
    $strategy = $this->langcodeMappingStrategy();
    $this->assertFalse(
      $this->isTranslationImport($strategy),
      'Nothing can be a translation import while content_translation is absent.',
    );

    mukurtu_import_update_40401();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'protocol_aware_content', TRUE);

    $this->assertTrue(
      $this->isTranslationImport($this->langcodeMappingStrategy()),
      'The guard is suppressing translation imports even after the module was installed.',
    );
  }

}
