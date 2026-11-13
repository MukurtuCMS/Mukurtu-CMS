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
 * Tests the attributes that turn on one-at-a-time uploads for a file field.
 *
 * The mukurtu_core/sequential-upload library finds the field, and its size
 * limit, on the field's wrapper.
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
    'file',
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
    $form['files'] = [
      '#type' => 'managed_file',
      '#title' => 'Files',
      '#multiple' => TRUE,
      '#attributes' => SequentialUpload::attributes(),
    ];
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
   * Tests that the field's wrapper carries the class and the server's limit.
   */
  public function testFieldWrapperAttributes(): void {
    $form = $this->container->get('form_builder')->getForm($this);
    $this->render($form);

    $fields = $this->xpath('//div[contains(concat(" ", @class, " "), " js-form-managed-file ") and contains(concat(" ", @class, " "), " js-mukurtu-sequential-upload ")]');
    $this->assertCount(1, $fields);

    $max = (int) Environment::getUploadMaxSize();
    $this->assertGreaterThan(0, $max);
    $this->assertSame((string) $max, (string) $fields[0]['data-mukurtu-max-filesize']);
    $this->assertSame((string) ByteSizeMarkup::create($max), (string) $fields[0]['data-mukurtu-max-filesize-label']);
  }

}
