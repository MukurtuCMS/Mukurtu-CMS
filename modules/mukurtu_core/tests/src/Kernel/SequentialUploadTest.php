<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\SequentialUpload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests what turns on one-at-a-time uploads for a file field.
 *
 * The mukurtu_core/sequential-upload library finds the field by its wrapper's
 * class, and reads the size limit from drupalSettings.
 */
#[CoversClass(SequentialUpload::class)]
#[Group('mukurtu_core')]
class SequentialUploadTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_sequential_upload_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['files'] = SequentialUpload::apply([
      '#type' => 'managed_file',
      '#title' => 'Files',
      '#multiple' => TRUE,
      '#attributes' => ['name' => 'kept'],
    ]);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Tests the field's class, library, and size limit.
   */
  public function testApply(): void {
    $form = $this->container->get('form_builder')->getForm($this);
    $field = $form['files'];
    // Other attributes stay.
    $this->assertSame('kept', $field['#attributes']['name']);

    // The field renders its attachments, so AJAX responses that replace it
    // carry them too.
    $this->assertContains('mukurtu_core/sequential-upload', $field['#attached']['library']);
    $max = (int) Environment::getUploadMaxSize();
    $this->assertGreaterThan(0, $max);
    $this->assertSame([
      'maxFilesize' => $max,
      'maxFilesizeLabel' => (string) ByteSizeMarkup::create($max),
    ], $field['#attached']['drupalSettings']['mukurtuSequentialUpload']);

    // The library finds the field by its wrapper's class.
    $this->render($form);
    $fields = $this->xpath('//div[contains(concat(" ", @class, " "), " js-form-managed-file ") and contains(concat(" ", @class, " "), " js-mukurtu-sequential-upload ")]');
    $this->assertCount(1, $fields);
  }

}
