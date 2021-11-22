<?php

namespace Drupal\mukurtu_collection\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\RevisionableInterface;
use Exception;

/**
 * VBO for adding content to collections.
 *
 * @Action(
 *   id = "mukurtu_add_to_collection_action",
 *   label = @Translation("Add to Collection"),
 *   type = "",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = FALSE,
 *   },
 * )
 */
class MukurtuAddToCollectionAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;


  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    // Get the collection.
    $collectionId = $this->configuration['collection'];
    $collection = \Drupal::entityTypeManager()->getStorage('node')->load($collectionId);

    // Add the entity to the collection.
    if ($collection && $collection->bundle() == 'collection' && $collection->access('update')) {
      $items = $collection->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
      $items[] = ['target_id' => $entity->id()];
      $collection->set(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $items);

      // Add revision message if supported.
      if ($collection instanceof RevisionableInterface) {
        $collection->setRevisionLogMessage($this->t("Added @node to collection.", ['@node' => $entity->getTitle()]));
      }

      try {
        // Validate the entity.
        $violations = $collection->validate();
        // Don't save if there are violations.
        if ($violations->count() > 0) {
          $this->messenger()->addError($violations[0]->getMessage());
          return $this->t("Failed to add an item to the collection");
        } else {
          $collection->save();
        }
      } catch (Exception $e) {
        return $this->t("Failed to add an item to the collection");
      }
    }

    return $this->t('Item added to the collection.');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof NodeInterface) {
      // Access is simply view access.
      return $object->access('view', $account, $return_as_object);
    }

    return $return_as_object ? FALSE : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account = NULL, ViewExecutable $view) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $collections = $this->getCollections();
    if (!empty($collections)) {
      foreach ($collections as $collection) {
        $options[$collection->id()] = $collection->getTitle();
      }

      $form['collection'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Collection'),
        '#options' => $options,
      ];
    }

    return $form;
  }

  /**
   * Return a list of collections user can update.
   */
  protected function getCollections() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->sort('changed', 'DESC');
    $allCollections = $query->execute();

    $collections = [];
    if (!empty($allCollections)) {
      $collections = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($allCollections);
      foreach ($collections as $delta => $collection) {
        // Remove collections the user cannot update.
        if (!$collection->access('update')) {
          unset($collections[$delta]);
          continue;
        }
      }
    }

    return $collections;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['collection'] = $form_state->getValue('collection');
  }

}
