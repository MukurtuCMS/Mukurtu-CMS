<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_core\MukurtuUserListBuilder;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;

/**
 * Tests that the "Submission Forms" service account never confuses an
 * admin browsing /admin/people: it's excluded from the user_admin_people
 * View's query (the actual mechanism behind /admin/people - see
 * mukurtu_submissions_views_query_alter()) and from
 * MukurtuUserListBuilder::load() (the underlying entity-type list builder,
 * in case anything else ever uses it), and if it's ever surfaced another
 * way its status reads as "blocked" rather than "pending" (see
 * mukurtu_submissions_create_service_account() and
 * mukurtu_submissions_update_40009()).
 *
 * field_pending is mukurtu_core's own approval-workflow field (DB default
 * 1) - this test defines an equivalent field directly on the user entity
 * rather than installing mukurtu_core itself, which has a long list of
 * hard dependencies MukurtuSubmissionsKernelTestBase deliberately
 * excludes; the field's *behavior*, not the rest of mukurtu_core, is what's
 * under test.
 *
 * @group mukurtu_submissions
 */
class ServiceAccountVisibilityTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
  }

  /**
   * Only called by tests that need field_pending to actually exist -
   * testUpdateHookNoOpWhenFieldMissing deliberately skips this to simulate
   * a site without mukurtu_core's field.
   */
  protected function installPendingField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_pending',
      'entity_type' => 'user',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_pending',
      'entity_type' => 'user',
      'bundle' => 'user',
      'default_value' => [['value' => 1]],
    ])->save();
  }

  /**
   * Loads the user list builder directly, bypassing mukurtu_core's own
   * hook_entity_type_alter() registration - it isn't installed here, but
   * the class itself is always autoloadable, and this is exactly the class
   * a real site's /admin/people uses.
   */
  protected function getUserListBuilder(): MukurtuUserListBuilder {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('user');
    return MukurtuUserListBuilder::createInstance($this->container, $entity_type);
  }

  public function testServiceAccountExcludedFromAdminList(): void {
    $this->setUpCurrentUser([], ['administer users']);

    $service_account = User::create(['name' => 'Submission Forms', 'status' => 0]);
    $service_account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $service_account->id())
      ->save();

    $ordinary_blocked_user = User::create(['name' => 'A Real Person', 'status' => 0]);
    $ordinary_blocked_user->save();

    $entities = $this->getUserListBuilder()->load();

    $this->assertArrayNotHasKey($service_account->id(), $entities);
    $this->assertArrayHasKey($ordinary_blocked_user->id(), $entities);
  }

  public function testAdminListUnaffectedWithoutServiceAccountConfigured(): void {
    $this->setUpCurrentUser([], ['administer users']);

    $ordinary_blocked_user = User::create(['name' => 'A Real Person', 'status' => 0]);
    $ordinary_blocked_user->save();

    $entities = $this->getUserListBuilder()->load();

    $this->assertArrayHasKey($ordinary_blocked_user->id(), $entities);
  }

  /**
   * @dataProvider providePeopleViewIds
   */
  public function testViewsQueryAlterExcludesServiceAccount(string $view_id): void {
    $service_account = User::create(['name' => 'Submission Forms', 'status' => 0]);
    $service_account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $service_account->id())
      ->save();

    $view = View::create(['id' => $view_id, 'base_table' => 'users_field_data']);
    $executable = $view->getExecutable();
    $query = $this->container->get('plugin.manager.views.query')->createInstance('views_query');

    mukurtu_submissions_views_query_alter($executable, $query);

    $conditions = $query->where[0]['conditions'] ?? [];
    $matches = array_filter($conditions, fn($condition) => $condition['field'] === 'users_field_data.uid'
      && $condition['value'] === (int) $service_account->id()
      && $condition['operator'] === '<>');
    $this->assertNotEmpty($matches);
  }

  /**
   * Both admin "People" listings (/admin/people and /admin/people/list)
   * need the exclusion - see MUKURTU_SUBMISSIONS_PEOPLE_VIEWS.
   */
  public static function providePeopleViewIds(): array {
    return [
      'user_admin_people (/admin/people)' => ['user_admin_people'],
      'mukurtu_people (/admin/people/list)' => ['mukurtu_people'],
    ];
  }

  public function testViewsQueryAlterIgnoresOtherViews(): void {
    $view = View::create(['id' => 'some_other_view', 'base_table' => 'node_field_data']);
    $executable = $view->getExecutable();
    $query = $this->container->get('plugin.manager.views.query')->createInstance('views_query');

    mukurtu_submissions_views_query_alter($executable, $query);

    $this->assertEmpty($query->where[0]['conditions'] ?? []);
  }

  public function testViewsQueryAlterNoOpWithoutServiceAccountConfigured(): void {
    $view = View::create(['id' => 'user_admin_people', 'base_table' => 'users_field_data']);
    $executable = $view->getExecutable();
    $query = $this->container->get('plugin.manager.views.query')->createInstance('views_query');

    mukurtu_submissions_views_query_alter($executable, $query);

    $this->assertEmpty($query->where[0]['conditions'] ?? []);
  }

  public function testServiceAccountCreatedNotPending(): void {
    $this->installPendingField();
    $account = mukurtu_submissions_create_service_account();
    $this->assertEquals(0, (int) $account->get('field_pending')->value);
  }

  public function testUpdateHookClearsExistingPendingServiceAccount(): void {
    $this->installPendingField();
    $account = User::create(['name' => 'Submission Forms', 'status' => 0, 'field_pending' => 1]);
    $account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $account->id())
      ->save();

    mukurtu_submissions_update_40009();

    $account = User::load($account->id());
    $this->assertEquals(0, (int) $account->get('field_pending')->value);
  }

  public function testUpdateHookNoOpWithoutServiceAccountConfigured(): void {
    mukurtu_submissions_update_40009();
    $this->assertTrue(TRUE);
  }

  public function testUpdateHookNoOpWhenFieldMissing(): void {
    $account = User::create(['name' => 'Submission Forms', 'status' => 0]);
    $account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $account->id())
      ->save();

    mukurtu_submissions_update_40009();
    $this->assertTrue(TRUE);
  }

}
