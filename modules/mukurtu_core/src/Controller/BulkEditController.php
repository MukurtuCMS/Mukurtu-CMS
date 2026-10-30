<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mukurtu_core\BulkEdit\BulkEditColumns;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sends the bulk edit pages' base paths to their first type.
 */
class BulkEditController extends ControllerBase {

  public function __construct(
    protected readonly BulkEditColumns $columns,
    protected readonly BulkEditColumns $mediaColumns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_core.bulk_edit_columns'),
      $container->get('mukurtu_core.bulk_edit_media_columns'),
    );
  }

  /**
   * Redirects to the first content type with a bulk edit page.
   */
  public function redirectToFirstType(): RedirectResponse {
    return $this->redirectToFirst($this->columns);
  }

  /**
   * Redirects to the first media type with a bulk edit page.
   */
  public function redirectToFirstMediaType(): RedirectResponse {
    return $this->redirectToFirst($this->mediaColumns);
  }

  /**
   * Redirects to a bulk edit page's first type.
   */
  protected function redirectToFirst(BulkEditColumns $columns): RedirectResponse {
    $type = array_key_first($columns->types());
    if (!$type) {
      throw new NotFoundHttpException();
    }
    return new RedirectResponse(Url::fromRoute($columns->route(), ['arg_0' => $type])->toString());
  }

}
