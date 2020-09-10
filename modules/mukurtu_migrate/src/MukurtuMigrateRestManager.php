<?php

namespace Drupal\mukurtu_migrate;

class MukurtuMigrateRestManager {
  protected $sourceUrl;
  protected $sourceUser;
  protected $sourcePassword;

  public function __construct() {
  }

  public function test() {
    $this->login();
  }

  public function fetchBatch($endpoint, $offset = 0, $number = 10, $itemsPerPage = 20) {
    $batch = [];
    $page = 0;
    $data = [];
    if ($offset >= $itemsPerPage) {
      $page = intdiv($offset, $itemsPerPage);
      $data = ['page' => $page];
    }

    $pageOffset = $offset - ($page * $itemsPerPage);
    $response = $this->remoteCall('GET', $endpoint, $data);
    if ($response['HTTP_CODE'] == 200) {
      $responseData = json_decode($response['body']);
      if (empty($responseData) || count($responseData) == 0) {
        return $batch;
      }
      $batch = array_slice($responseData, $pageOffset, $number);
      $size = count($batch);

      if ($size > 0 && $number - $size > 0) {
        return array_merge($batch, $this->fetchBatch($endpoint, $offset + $size, $number - $size, $itemsPerPage));
      }
    }

    return $batch;
  }

  public function login($url, $user, $password) {
    $this->sourceUrl = $url;
    $this->sourceUser = $user;
    $this->sourcePassword = $password;

    return $this->authenticate();
  }

  public function authenticate() {
    $data = [
      'username' => $this->sourceUser,
      'password' => $this->sourcePassword,
    ];
    $response = $this->remoteCall("POST", "/user/login", $data);
    if ($response['HTTP_CODE'] == 200) {
      return TRUE;
    }
    return FALSE;
  }

  private function remoteCall($method, $endpoint, $data = FALSE, $retry = TRUE) {
    $url = $this->sourceUrl . $endpoint;
    $http_header = [];

    $curl = curl_init();

    switch ($method) {
      case "DELETE":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;

      case "PATCH":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');

        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        break;

      case "POST":
        curl_setopt($curl, CURLOPT_POST, 1);

        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        break;

      case "PUT":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        break;

      default:
        if ($data)
          $url = sprintf("%s?%s", $url, http_build_query($data));
    }


    curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    // Send the request.
    $result['body'] = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $result['HTTP_CODE'] = $http_code;
    curl_close($curl);

    // If request was forbidden and auth was selected, try re-authenticated and repeat the request.
    if (($http_code == 403 || $http_code == 401) && $retry) {
      return $this->remoteCall($method, $data, FALSE);
    }

    return $result;
  }
}
