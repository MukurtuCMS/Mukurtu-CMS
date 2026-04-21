<?php

namespace Drupal\Tests\features_ui\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the creation of a feature.
 *
 * @group features_ui
 */
class FeaturesCreateUiTest extends BrowserTestBase {

  /**
   * The variable.
   *
   * @var mixed
   * @todo Remove the disabled strict config schema checking.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'features', 'features_ui'];

  /**
   * Tests creating a feature via UI and download it.
   */
  public function testCreateFeaturesUi() {
    $feature_name = 'test_feature2';
    $admin_user = $this->createUser([
      'administer site configuration',
      'export configuration',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalGet('admin/config/development/features');
    $this->clickLink('Create new feature');
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'name' => 'Test feature',
      'machine_name' => $feature_name,
      'description' => 'Test description: <strong>giraffe</strong>',
      'version' => '1.0.0',
      'system_simple[sources][selected][system.theme]' => TRUE,
      'system_simple[sources][selected][user.settings]' => TRUE,
    ];
    $this->submitForm($edit, 'Download Archive');

    $this->assertSession()->statusCodeEquals(200);
    $archive = $this->getSession()->getPage()->getContent();
    $filename = tempnam($this->tempFilesDirectory, 'feature');
    file_put_contents($filename, $archive);

    $archive = new ArchiveTar($filename);
    $files = $archive->listContent();

    $this->assertEquals(4, count($files));
    $this->assertEquals($feature_name . '/' . $feature_name . '.info.yml', $files[0]['filename']);
    $this->assertEquals($feature_name . '/' . $feature_name . '.features.yml', $files[1]['filename']);
    $this->assertEquals($feature_name . '/config/install/system.theme.yml', $files[2]['filename']);
    $this->assertEquals($feature_name . '/config/install/user.settings.yml', $files[3]['filename']);

    // Ensure that the archive contains the expected values.
    $info_filename = tempnam($this->tempFilesDirectory, 'feature');
    file_put_contents($info_filename, $archive->extractInString($feature_name . '/' . $feature_name . '.info.yml'));
    $features_info_filename = tempnam($this->tempFilesDirectory, 'feature');
    file_put_contents($features_info_filename, $archive->extractInString($feature_name . '/' . $feature_name . '.features.yml'));
    $parsed_info = Yaml::decode(file_get_contents($info_filename));
    $this->assertEquals('Test feature', $parsed_info['name']);
    $parsed_features_info = Yaml::decode(file_get_contents($features_info_filename));
    $this->assertEquals([
      'required' => TRUE,
    ], $parsed_features_info);

    $archive->extract(\Drupal::service('kernel')->getSitePath() . '/modules');
    $module_path = \Drupal::service('kernel')->getSitePath() . '/modules/' . $feature_name;

    // Ensure that the features listing renders the right content.
    $this->drupalGet('admin/config/development/features');
    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertSession()->linkExists('Test feature');
    $this->assertEquals($feature_name, $tds[2]->getText());
    $description_column = $tds[3]->getText();
    $this->assertTrue(strpos($description_column, 'system.theme') !== FALSE);
    $this->assertTrue(strpos($description_column, 'user.settings') !== FALSE);
    $this->assertSession()->responseContains('Test description: <strong>giraffe</strong>');
    $this->assertEquals('Uninstalled', $tds[5]->getText());
    $this->assertEquals('', $tds[6]->getText());

    // Remove one and add new configuration.
    $this->clickLink('Test feature');
    $edit = [
      'system_simple[included][system.theme]' => FALSE,
      'user_role[sources][selected][authenticated]' => TRUE,
    ];
    $this->submitForm($edit, 'Write');
    $info_filename = $module_path . '/' . $feature_name . '.info.yml';

    $parsed_info = Yaml::decode(file_get_contents($info_filename));
    $this->assertEquals('Test feature', $parsed_info['name']);

    $features_info_filename = $module_path . '/' . $feature_name . '.features.yml';
    $parsed_features_info = Yaml::decode(file_get_contents($features_info_filename));
    $this->assertEquals([
      'excluded' => ['system.theme'],
      'required' => TRUE,
    ], $parsed_features_info);

    $this->drupalGet('admin/modules');
    $page = $this->getSession()->getPage();
    $this->assertStringContainsString('Test feature', $page->getContent());

    // Install new feature module.
    $edit = [];
    $edit['modules[' . $feature_name . '][enable]'] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // Check that the feature is listed as installed.
    $this->drupalGet('admin/config/development/features');

    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertEquals('Installed', $tds[5]->getText());

    // Check that a config change results in a feature marked as changed.
    // Set to a string without whitespace to avoid the complication of added
    // quotation marks showing up in the diff.
    \Drupal::configFactory()->getEditable('user.settings')
      ->set('anonymous', 'Giraffe')
      ->save();

    $this->drupalGet('admin/config/development/features');

    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertTrue(strpos($tds[6]->getText(), 'Changed') !== FALSE);
    $this->drupalGet('admin/modules/uninstall');

    // Uninstall the module.
    $this->submitForm(['uninstall[' . $feature_name . ']' => $feature_name], 'Uninstall');
    $this->submitForm([], 'Uninstall');

    $this->drupalGet('admin/config/development/features');

    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertTrue(strpos($tds[6]->getText(), 'Changed') !== FALSE);

    $this->clickLink('Changed');
    $this->drupalGet('admin/config/development/features/diff/' . $feature_name);
    $this->assertSession()->responseContains('<td class="diff-context diff-deletedline">anonymous : <span class="diffchange">Giraffe</span></td>');
    $this->assertSession()->responseContains('<td class="diff-context diff-addedline">anonymous : <span class="diffchange">Anonymous</span></td>');

    $edit = [];
    $edit['modules[' . $feature_name . '][enable]'] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    $this->drupalGet('admin/config/development/features');
    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertEquals('Installed', $tds[5]->getText());

    // Ensure that the changed config got overridden.
    $this->assertEquals('Anonymous', \Drupal::config('user.settings')->get('anonymous'));

    // Change the value, export and ensure that its not shown as changed.
    \Drupal::configFactory()->getEditable('user.settings')
      ->set('anonymous', 'Giraffe')
      ->save();

    // Ensure that exporting this change will result in an unchanged feature.
    $this->drupalGet('admin/config/development/features');
    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertTrue(strpos($tds[6]->getText(), 'Changed') !== FALSE);

    $this->clickLink('Test feature');
    $this->submitForm([], 'Write');

    $this->drupalGet('admin/config/development/features');
    $tds = $this->xpath('//table[contains(@class, "features-listing")]/tbody/tr[td[3] = "' . $feature_name . '"]/td');
    $this->assertEquals('Installed', $tds[5]->getText());
  }

}
