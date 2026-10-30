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
 * Sends /admin/content/bulk to the first content type's bulk edit page.
 */
class BulkEditController extends ControllerBase {

  public function __construct(
    protected readonly BulkEditColumns $columns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_core.bulk_edit_columns'));
  }

  /**
   * Redirects to the first content type with a bulk edit page.
   */
  public function redirectToFirstType(): RedirectResponse {
    $type = array_key_first($this->columns->types());
    if (!$type) {
      throw new NotFoundHttpException();
    }
    return new RedirectResponse(Url::fromRoute('view.' . BulkEditColumns::VIEW_ID . '.page', ['arg_0' => $type])->toString());
  }

}
