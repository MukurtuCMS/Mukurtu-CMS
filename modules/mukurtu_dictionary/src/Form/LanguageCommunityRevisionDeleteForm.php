<?php

namespace Drupal\mukurtu_dictionary\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Language community revision.
 *
 * @ingroup mukurtu_dictionary
 */
class LanguageCommunityRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Language community revision.
   *
   * @var \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface
   */
  protected $revision;

  /**
   * The Language community storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $languageCommunityStorage;

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
    $instance->languageCommunityStorage = $container->get('entity_type.manager')->getStorage('language_community');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_community_revision_delete_confirm';
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
    return new Url('entity.language_community.version_history', ['language_community' => $this->revision->id()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, $language_community_revision = NULL) {
    $this->revision = $this->LanguageCommunityStorage->loadRevision($language_community_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->LanguageCommunityStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Language community: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Language community %title has been deleted.', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.language_community.canonical',
       ['language_community' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {language_community_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.language_community.version_history',
         ['language_community' => $this->revision->id()]
      );
    }
  }

}
