<?php

namespace Drupal\custom_media\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;

/**
 * Plugin implementation of the 'custom_media_image' formatter.
 *
 * @FieldFormatter(
 *   id = "custom_media_image",
 *   label = @Translation("custom_media.image_formatter.label"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CustomMediaImageFormatter extends MediaThumbnailFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    if (empty($elements)) {
      return $elements;
    }

    $media_items = $this->getEntitiesToView($items, $langcode);
    foreach ($media_items as $delta => $media_item) {
      // Get name of current source_field.
      $sourcePluginCollection = $media_item->bundle->entity->getPluginCollections();
      $configuration = $sourcePluginCollection['source_configuration']->getConfiguration();

      $elements[$delta]['#item'] = $media_item->get($configuration['source_field']);
    }

    return $elements;
  }

}
