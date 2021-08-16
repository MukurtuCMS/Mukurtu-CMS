<?php

namespace Drupal\mukurtu_roundtrip\Services;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class Importer {
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
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

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_manager;

  public function __construct(PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user, EntityTypeManagerInterface $entity_manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->store = $this->tempStoreFactory->get('mukurtu_roundtrip_importer');
    $this->entity_manager = $entity_manager;
  }

  public function getInputFiles() {
    return $this->store->get('user_input_files');
  }

  public function setInputFiles($files) {
    $this->store->set('user_input_files', $files);
  }

  public function unpack() {
    $inputFiles = $this->getInputFiles();
    if (!empty($inputFiles)) {

    }


  }

  protected function reset() {
    // TODO: Delete temp files?

    // Reset our variables.
    $this->store->set('user_input_files', []);
  }

  public function import(array $fids) {

  }

}
