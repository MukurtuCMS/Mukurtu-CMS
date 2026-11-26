<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that visitors submit 3D models only where a site allows it (#2810).
 *
 * Wherever no media types are chosen, which otherwise means all of them,
 * 3D models are left out.
 *
 * @see \Drupal\mukurtu_submissions\Entity\SubmissionSettings::OPT_IN_MEDIA_TYPES
 * @see mukurtu_submissions_update_40202()
 */
#[Group('mukurtu_submissions')]
class ThreeDModelSubmissionTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Media, so the media permissions the update grants exist; core drops
   * unknown permissions when a role is saved.
   */
  protected static $modules = ['image', 'media'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['media']);
    foreach (['document' => 'Document', '3d_model' => '3D Model'] as $id => $label) {
      MediaType::create([
        'id' => $id,
        'label' => $label,
        'source' => 'file',
        'source_configuration' => ['source_field' => 'field_media_' . $id],
      ])->save();
    }
  }

  /**
   * The opt-in types come off a list, which otherwise keeps its order.
   */
  public function testWithoutOptInMediaTypes(): void {
    $types = SubmissionSettings::withoutOptInMediaTypes(['image', '3d_model', 'document']);
    $this->assertSame(['image', 'document'], $types);
    $this->assertSame(['image'], SubmissionSettings::withoutOptInMediaTypes(['image']));
  }

  /**
   * The upload widget takes 3D models only when they're listed.
   */
  public function testUploadWidgetBundles(): void {
    $definition = BaseFieldDefinition::create('entity_reference')
      ->setName('field_media_assets')
      ->setSetting('target_type', 'media');
    $widget = function (array $allowed) use ($definition) {
      return \Drupal::service('plugin.manager.field.widget')->createInstance('mukurtu_simple_media_upload', [
        'field_definition' => $definition,
        'settings' => ['allowed_bundles' => $allowed],
        'third_party_settings' => [],
      ]);
    };

    $default = $widget([])->getAllowedBundles();
    $this->assertContains('document', $default);
    $this->assertNotContains('3d_model', $default);

    $this->assertContains('3d_model', $widget(['document', '3d_model'])->getAllowedBundles());
  }

  /**
   * The submission form leaves 3D models out unless the settings list them.
   */
  public function testSubmissionFormRestriction(): void {
    $this->assertSame(['image', 'document'], $this->restrict([], ['image', 'document', '3d_model']));
    $this->assertNotContains('3d_model', $this->restrict([], []));
    $this->assertContains('image', $this->restrict([], []));
    $allowed = $this->restrict(['document', '3d_model'], ['image', 'document', '3d_model']);
    $this->assertSame(['document', '3d_model'], $allowed);
  }

  /**
   * Runs the form's media type restriction on a display with both widgets.
   *
   * @param string[] $settings_types
   *   The submission settings' allowed media types; empty means none chosen.
   * @param string[] $widget_types
   *   Both widgets' own media types; empty means the widget's default.
   *
   * @return string[]
   *   The upload widget's media types after the restriction. The media
   *   library widget is checked to match.
   */
  private function restrict(array $settings_types, array $widget_types): array {
    $form = \Drupal::classResolver(PublicSubmissionForm::class);
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => static::TEST_BUNDLE,
      'mode' => 'submission',
      'status' => TRUE,
    ]);
    $display->setComponent('field_library', [
      'type' => 'media_library_widget',
      'settings' => ['media_types' => $widget_types],
    ]);
    $display->setComponent('field_upload', [
      'type' => 'mukurtu_simple_media_upload',
      'settings' => ['allowed_bundles' => $widget_types],
    ]);
    $settings = SubmissionSettings::create([
      'id' => 'test',
      'label' => 'Test',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'allowed_media_types' => $settings_types,
    ]);
    foreach (['display' => $display, 'settings' => $settings, 'entity' => Node::create(['type' => static::TEST_BUNDLE])] as $property => $value) {
      $reflection = new \ReflectionProperty($form, $property);
      $reflection->setValue($form, $value);
    }
    (new \ReflectionMethod($form, 'restrictMediaTypes'))->invoke($form);

    $upload = $display->getComponent('field_upload')['settings']['allowed_bundles'];
    $library = $display->getComponent('field_library')['settings']['media_types'];
    if ($widget_types) {
      $this->assertSame($upload, $library, 'Both widgets were restricted the same way.');
    }
    else {
      $this->assertNotContains('3d_model', $library);
    }
    return $upload;
  }

  /**
   * The roles that review submissions get rights over submitted 3D models.
   */
  public function testReviewerRoleUpdate(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    $reviewer = Role::create(['id' => 'mukurtu_submission_reviewer', 'label' => 'Submission Reviewer']);
    $reviewer->grantPermission('edit any document media')->grantPermission('delete any document media')->save();
    // A role a site never gave document rights to is left alone.
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->assertSame('Let submission reviewers manage submitted 3D models.', mukurtu_submissions_update_40202());

    $reviewer = Role::load('mukurtu_submission_reviewer');
    $this->assertTrue($reviewer->hasPermission('edit any 3d_model media'));
    $this->assertTrue($reviewer->hasPermission('delete any 3d_model media'));
    $this->assertFalse(Role::load('mukurtu_manager')->hasPermission('edit any 3d_model media'));

    $this->assertNull(mukurtu_submissions_update_40202(), 'A second run changed something.');
  }

  /**
   * The update runs after the one that creates the 3D Model media type.
   *
   * Without the declared dependency the two modules' updates aren't linked,
   * and only the modules' names put them in the right order.
   */
  public function testRunsAfterMediaTypeUpdate(): void {
    require_once $this->root . '/core/includes/update.inc';
    // A site from before both updates. Drupal reads hook_update_dependencies()
    // only from modules with a schema version, which kernel tests don't set.
    $core_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $this->root . '/' . $core_path . '/mukurtu_core.install';
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    $registry = \Drupal::service('update.update_hook_registry');
    $registry->setInstalledVersion('mukurtu_core', 40232);
    $registry->setInstalledVersion('mukurtu_submissions', 40201);

    $graph = update_build_dependency_graph(update_get_update_function_list([
      'mukurtu_core' => 40233,
      'mukurtu_submissions' => 40202,
    ]));
    $this->assertArrayHasKey('mukurtu_submissions_update_40202', $graph['mukurtu_core_update_40233']['edges'] ?? []);
  }

}
