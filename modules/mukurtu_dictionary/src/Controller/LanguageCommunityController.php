<?php

namespace Drupal\mukurtu_dictionary\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LanguageCommunityController.
 *
 *  Returns responses for Language community routes.
 */
class LanguageCommunityController extends ControllerBase implements ContainerInjectionInterface {

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
   * Displays a Language community revision.
   *
   * @param int $language_community_revision
   *   The Language community revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($language_community_revision) {
    $language_community = $this->entityTypeManager()->getStorage('language_community')
      ->loadRevision($language_community_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('language_community');

    return $view_builder->view($language_community);
  }

  /**
   * Page title callback for a Language community revision.
   *
   * @param int $language_community_revision
   *   The Language community revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($language_community_revision) {
    $language_community = $this->entityTypeManager()->getStorage('language_community')
      ->loadRevision($language_community_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $language_community->label(),
      '%date' => $this->dateFormatter->format($language_community->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Language community.
   *
   * @param \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface $language_community
   *   A Language community object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(LanguageCommunityInterface $language_community) {
    $account = $this->currentUser();
    $language_community_storage = $this->entityTypeManager()->getStorage('language_community');

    $langcode = $language_community->language()->getId();
    $langname = $language_community->language()->getName();
    $languages = $language_community->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $language_community->label()]) : $this->t('Revisions for %title', ['%title' => $language_community->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all language community revisions") || $account->hasPermission('administer language community entities')));
    $delete_permission = (($account->hasPermission("delete all language community revisions") || $account->hasPermission('administer language community entities')));

    $rows = [];

    $vids = $language_community_storage->revisionIds($language_community);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\mukurtu_dictionary\LanguageCommunityInterface $revision */
      $revision = $language_community_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $language_community->getRevisionId()) {
          $link = $this->l($date, new Url('entity.language_community.revision', [
            'language_community' => $language_community->id(),
            'language_community_revision' => $vid,
          ]));
        }
        else {
          $link = $language_community->link($date);
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
              Url::fromRoute('entity.language_community.translation_revert', [
                'language_community' => $language_community->id(),
                'language_community_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.language_community.revision_revert', [
                'language_community' => $language_community->id(),
                'language_community_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.language_community.revision_delete', [
                'language_community' => $language_community->id(),
                'language_community_revision' => $vid,
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

    $build['language_community_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
