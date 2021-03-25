<?php

namespace Drupal\mukurtu_roundtrip\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\InvokeCommand;

class ImportTabulatorAjaxController {
/*   public function access(AccountInterface $account, NodeInterface $node) {
  } */

  protected function toCSV($rows) {
    $buffer = fopen('php://temp', 'a+');

    // Build the column headers.
    if (isset($rows[0])) {
      $row = $rows[0];
      $headers = [];
      foreach ($row as $field => $value) {
        if ($field == 'id') {
          continue;
        }
        $headers[$field] = $field;
      }
      fputcsv($buffer, $headers);
    }

    // Write the rows.
    foreach ($rows as $row) {
      if (isset($row['id'])) {
        unset($row['id']);
      }

      fputcsv($buffer, $row);
    }
    rewind($buffer);
    $csv = stream_get_contents($buffer);
    fclose($buffer);

    return $csv;
  }

  public function fileUpdate(Request $request) {
    $response = new AjaxResponse();

    $fid = $request->request->get('fid');
    $rows = $request->request->get('rows');

    $csv = $this->toCSV(json_decode($rows, TRUE));

    if ($fid) {
      $file = \Drupal\file\Entity\File::load($fid);
      if ($file) {
        file_save_data($csv, $file->getFileUri(), \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
        $file->setTemporary();
        $file->save();
      }
    }

    // Enable the validate button.
    $response->addCommand(new InvokeCommand('#edit-submitforvalidation', 'prop', ['disabled', FALSE]));

    return $response;
  }
}
