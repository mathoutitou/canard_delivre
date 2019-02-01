<?php

namespace Drupal\custom_media\Plugin\EntityBrowser\Widget;

/**
 * Create media entity videos embed field.
 *
 * @EntityBrowserWidget(
 *   id = "custom_media_video_embed_field_add",
 *   label = @Translation("custom.media.entity_browser.video_embed_field.widget.label"),
 *   description = @Translation("custom.media.entity_browser.video_embed_field.widget.description"),
 *   auto_select = FALSE
 * )
 */
class MediaVideoEmbedFieldAdd extends AbstractMediaAdd {

  /**
   * {@inheritdoc}
   */
  const SOURCE = 'video_embed_field';

}
