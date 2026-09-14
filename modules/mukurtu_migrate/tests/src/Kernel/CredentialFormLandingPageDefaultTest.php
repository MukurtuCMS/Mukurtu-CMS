<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_migrate\Form\CredentialForm;

/**
 * Tests the "Create default landing page" checkbox's default value.
 *
 * It must default to checked, so a site builder who clicks through the
 * migration wizard without touching it still gets a homepage rather than
 * silently opting out.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154
 */
class CredentialFormLandingPageDefaultTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'migrate_drupal_ui',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // CredentialForm::buildForm() only builds its fields when the wizard's
    // tempstore is on the 'credential' step; otherwise it redirects back to
    // the overview form instead.
    \Drupal::service('tempstore.private')
      ->get('mukurtu_migrate')
      ->set('step', 'credential');
  }

  /**
   * Tests that the checkbox is checked by default.
   */
  public function testCreateLandingPageDefaultsToChecked(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(TestCredentialForm::class, $form_state);

    $this->assertArrayHasKey('create_landing_page', $form);
    $this->assertTrue($form['create_landing_page']['#default_value']);
  }

}

/**
 * A CredentialForm that skips the constructor's site-content check.
 *
 * MukurtuMigrateFormBase::__construct() unconditionally runs
 * siteHasContent(), which queries the node, media, community and protocol
 * entity types. None of that is relevant to the "Create default landing
 * page" checkbox's default value, so it's stubbed out here rather than
 * installing node/media/mukurtu_protocol (and, transitively, og) just to
 * satisfy a constructor side effect this test doesn't exercise.
 */
class TestCredentialForm extends CredentialForm {

  /**
   * {@inheritdoc}
   */
  protected function siteHasContent() {
    return FALSE;
  }

}
