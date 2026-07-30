<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

/**
 * Tests mukurtu_local_contexts_update_10004().
 *
 * @group mukurtu_local_contexts
 */
class Update10004Test extends LocalContextsTestBase {

  /**
   * Tests the update hook is safe to run when the columns already exist.
   *
   * This hook was renumbered during a merge (it originally ran as
   * update_10001 on some environments before three unrelated hooks were
   * inserted ahead of it), so it must tolerate being invoked against a
   * schema that already has the locale/language columns, exactly as
   * installSchema() produces here from the module's current hook_schema().
   */
  public function testUpdateHookSkipsExistingColumns(): void {
    require_once __DIR__ . '/../../../mukurtu_local_contexts.install';

    mukurtu_local_contexts_update_10004();

    $schema = $this->container->get('database')->schema();
    $this->assertTrue($schema->fieldExists('mukurtu_local_contexts_notices', 'locale'));
    $this->assertTrue($schema->fieldExists('mukurtu_local_contexts_notices', 'language'));
  }

  /**
   * Tests the update hook still adds the columns when they're missing.
   */
  public function testUpdateHookAddsMissingColumns(): void {
    require_once __DIR__ . '/../../../mukurtu_local_contexts.install';

    $schema = $this->container->get('database')->schema();
    $schema->dropField('mukurtu_local_contexts_notices', 'locale');
    $schema->dropField('mukurtu_local_contexts_notices', 'language');

    mukurtu_local_contexts_update_10004();

    $this->assertTrue($schema->fieldExists('mukurtu_local_contexts_notices', 'locale'));
    $this->assertTrue($schema->fieldExists('mukurtu_local_contexts_notices', 'language'));
  }

}
