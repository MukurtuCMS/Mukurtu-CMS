<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mukurtu_roundtrip\Services\Importer;
abstract class MukurtuImportFormBase extends FormBase {

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  protected $importer;

  protected $entityTypeManager;
  protected $fileStorage;
  protected $url;

  //public function __construct(PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
  public function __construct($importer, $entity_type_manager, $url) {
    /* $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->store = $this->tempStoreFactory->get('mukurtu_import_form'); */
    $this->importer = $importer;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileStorage = $this->entityTypeManager->getStorage('file');
    $this->url = $url;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mukurtu_roundtrip.importer'),
      $container->get('entity_type.manager'),
      $container->get('url_generator')
      /* $container->get('user.private_tempstore'),
      $container->get('current_user') */
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    return $form;
  }

  protected function transition($event) {

  }

}
