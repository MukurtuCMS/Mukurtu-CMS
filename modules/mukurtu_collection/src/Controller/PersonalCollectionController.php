<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_collection\Entity\PersonalCollectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PersonalCollectionController.
 *
 *  Returns responses for Personal collection routes.
 */
class PersonalCollectionController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Personal collection revision.
   *
   * @param int $personal_collection_revision
   *   The Personal collection revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($personal_collection_revision) {
    /** @var EntityStorageInterface $personal_collection */
    $personal_collection = $this->entityTypeManager()->getStorage('personal_collection')
      ->loadRevision($personal_collection_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('personal_collection');

    return $view_builder->view($personal_collection);
  }

  /**
   * Page title callback for a Personal collection revision.
   *
   * @param int $personal_collection_revision
   *   The Personal collection revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($personal_collection_revision) {
    /** @var EntityStorageInterface $personal_collection */
    $personal_collection = $this->entityTypeManager()->getStorage('personal_collection')
      ->loadRevision($personal_collection_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $personal_collection->label(),
      '%date' => $this->dateFormatter->format($personal_collection->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Personal collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface $personal_collection
   *   A Personal collection object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(PersonalCollectionInterface $personal_collection) {
    $account = $this->currentUser();
    $personal_collection_storage = $this->entityTypeManager()->getStorage('personal_collection');

    $langcode = $personal_collection->language()->getId();
    $langname = $personal_collection->language()->getName();
    $languages = $personal_collection->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $personal_collection->label()]) : $this->t('Revisions for %title', ['%title' => $personal_collection->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all personal collection revisions") || $account->hasPermission('administer personal collection entities')));
    $delete_permission = (($account->hasPermission("delete all personal collection revisions") || $account->hasPermission('administer personal collection entities')));

    $rows = [];

    $vids = $personal_collection_storage->revisionIds($personal_collection);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var PersonalCollectionInterface $revision */
      $revision = $personal_collection_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $personal_collection->getRevisionId()) {
          $link = $this->l($date, new Url('entity.personal_collection.revision', [
            'personal_collection' => $personal_collection->id(),
            'personal_collection_revision' => $vid,
          ]));
        }
        else {
          $link = $personal_collection->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.personal_collection.translation_revert', [
                'personal_collection' => $personal_collection->id(),
                'personal_collection_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.personal_collection.revision_revert', [
                'personal_collection' => $personal_collection->id(),
                'personal_collection_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.personal_collection.revision_delete', [
                'personal_collection' => $personal_collection->id(),
                'personal_collection_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['personal_collection_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
