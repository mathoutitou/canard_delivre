<?php

namespace Drupal\custom_default_content;

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\hal\Normalizer\FileEntityNormalizer as OriginalFileEntityNormalizer;
use GuzzleHttp\Exception\RequestException;

/**
 * Decorate FileEntityNormalizer class.
 */
class FileEntityNormalizer extends OriginalFileEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {

    // Check if file already exists in locale.
    $parse_url = parse_url($data['uri'][0]['value']);
    $path = file_default_scheme() . '://' . str_replace('/' . PublicStream::basePath() . '/', '', $parse_url['path']);

    if (file_exists($path)) {
      $data['_links']['self']['href'] = $path;
      $data['_links']['self']['href'] = $path;
      $data['uri'] = $path;

      return $this->entityManager->getStorage('file')->create($data);
    }

    try {
      return parent::denormalize($data, $class, $format, $context);
    }
    catch (RequestException $e) {
      // We already create file entity even though file wasn't uploaded.
      return $this->entityManager->getStorage('file')->create($data);
    }
  }

}
