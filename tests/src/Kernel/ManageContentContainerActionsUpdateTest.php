<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40034().
 *
 * The hook adds the Add to Collection and Add to Word List bulk actions to the
 * Manage content view on existing sites, keeping any actions the site has
 * added or removed.
 */
#[Group('mukurtu')]
class ManageContentContainerActionsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The stored view is written raw, as a real site's would be, without the
   * modules whose plugins it references. Only selected_actions matters here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The VBO field's selected_actions path on the default display.
   */
  protected const PATH = 'display.default.display_options.fields.views_bulk_operations_bulk_form.selected_actions';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Writes the Manage content view with the given selected actions.
   */
  protected function writeView(array $actions): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $data = (new FileStorage($profile_path . '/config/install'))->read('views.view.mukurtu_manage_all_content');
    $this->assertIsArray($data);
    $data['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['selected_actions'] = $actions;
    \Drupal::service('config.storage')->write('views.view.mukurtu_manage_all_content', $data);
    \Drupal::configFactory()->reset('views.view.mukurtu_manage_all_content');
  }

  /**
   * Returns the stored selected actions.
   */
  protected function storedActions(): array {
    return \Drupal::config('views.view.mukurtu_manage_all_content')->get(static::PATH);
  }

  /**
   * Both actions are appended and a customized list is otherwise kept.
   */
  public function testAppendsToCustomizedList(): void {
    // A site that removed Delete and added its own action.
    $this->writeView([
      4 => ['action_id' => 'mukurtu_export_add_to_list_action'],
      43 => ['action_id' => 'mukurtu_export_adhoc_export_action'],
      50 => ['action_id' => 'site_custom_action', 'preconfiguration' => ['label_override' => 'Custom']],
    ]);

    $message = mukurtu_update_40034();

    $this->assertSame([
      4 => ['action_id' => 'mukurtu_export_add_to_list_action'],
      43 => ['action_id' => 'mukurtu_export_adhoc_export_action'],
      50 => ['action_id' => 'site_custom_action', 'preconfiguration' => ['label_override' => 'Custom']],
      51 => ['action_id' => 'mukurtu_add_to_collection_action'],
      52 => ['action_id' => 'mukurtu_add_to_word_list_action'],
    ], $this->storedActions());
    $this->assertStringContainsString('Added', $message);
  }

  /**
   * An action the site already offers isn't added twice.
   */
  public function testSkipsActionAlreadyPresent(): void {
    $this->writeView([
      4 => ['action_id' => 'mukurtu_add_to_collection_action'],
    ]);

    mukurtu_update_40034();

    $this->assertSame([
      4 => ['action_id' => 'mukurtu_add_to_collection_action'],
      5 => ['action_id' => 'mukurtu_add_to_word_list_action'],
    ], $this->storedActions());
  }

  /**
   * Running twice is a no-op the second time.
   */
  public function testIsIdempotent(): void {
    $this->writeView([4 => ['action_id' => 'mukurtu_export_add_to_list_action']]);

    mukurtu_update_40034();
    $before = $this->storedActions();
    $message = mukurtu_update_40034();

    $this->assertSame($before, $this->storedActions());
    $this->assertStringContainsString('nothing to change', $message);
  }

  /**
   * A site without the view is left alone.
   */
  public function testMissingView(): void {
    $this->assertStringContainsString('not installed', mukurtu_update_40034());
    $this->assertTrue(\Drupal::config('views.view.mukurtu_manage_all_content')->isNew());
  }

  /**
   * The shipped view offers both actions, so new sites match updated ones.
   */
  public function testShippedViewOffersBothActions(): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $data = (new FileStorage($profile_path . '/config/install'))->read('views.view.mukurtu_manage_all_content');
    $ids = array_column($data['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['selected_actions'], 'action_id');

    $this->assertContains('mukurtu_add_to_collection_action', $ids);
    $this->assertContains('mukurtu_add_to_word_list_action', $ids);
  }

}
