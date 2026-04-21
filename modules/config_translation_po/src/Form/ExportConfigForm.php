<?php

namespace Drupal\config_translation_po\Form;

use Drupal\Component\Gettext\PoHeader;
use Drupal\Component\Gettext\PoStreamWriter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\locale\Form\ExportForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Provides a form for exporting configuration translations.
 */
class ExportConfigForm extends ExportForm {

  /**
   * Drupal\config_translation_po\Services\CtpConfigManager definition.
   *
   * @var \Drupal\config_translation_po\Services\CtpConfigManager
   */
  protected $cteiConfigManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cteiConfigManager = $container->get('ctp.config_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'export_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['content_options']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $langcode = $form_state->getValue('langcode');

    $names = $this->cteiConfigManager->getComponentNames([]);
    $items = $this->cteiConfigManager
      ->exportConfigTranslations($names, [$langcode]);

    if (!empty($items)) {
      $uri = $this->fileSystem->tempnam('temporary://', 'po_');
      $filename = $langcode . '.po';
      $header = new PoHeader($langcode);
      $header->setProjectName($this->config('system.site')->get('name'));
      $header->setLanguageName($langcode);

      $writer = new PoStreamWriter();
      $writer->setURI($uri);
      $writer->setHeader($header);

      $writer->open();
      foreach ($items as $item) {
        $writer->writeItem($item);
      }
      $writer->close();

      $response = new BinaryFileResponse($uri);
      $response->setContentDisposition('attachment', $filename);
      $form_state->setResponse($response);
    }
  }

}
