<?php

namespace Drupal\mukurtu_collection\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Personal collection revision.
 *
 * @ingroup mukurtu_collection
 */
class PersonalCollectionRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Personal collection revision.
   *
   * @var \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   */
  protected $revision;

  /**
   * The Personal collection storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $personalCollectionStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->personalCollectionStorage = $container->get('entity_type.manager')->getStorage('personal_collection');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'personal_collection_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.personal_collection.version_history', ['personal_collection' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $personal_collection_revision = NULL) {
    $this->revision = $this->PersonalCollectionStorage->loadRevision($personal_collection_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->PersonalCollectionStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Personal collection: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Personal collection %title has been deleted.', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.personal_collection.canonical',
       ['personal_collection' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {personal_collection_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.personal_collection.version_history',
         ['personal_collection' => $this->revision->id()]
      );
    }
  }

}
