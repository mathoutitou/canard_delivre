<?php

namespace Drupal\custom_homepage\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\custom_homepage\Form\HomepageSettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for default route.
 */
class DefaultController extends ControllerBase {

  /**
   * The entity query service.
   *
   * @var QueryFactory
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Load the services required to construct this class.
    return new static(
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param QueryFactory $entityQuery
   *   The entity query service.
   */
  public function __construct(QueryFactory $entityQuery) {
    $this->entityQuery = $entityQuery;
  }

  /**
   * Display homepage.
   *
   * @return array
   *   The page content to render.
   */
  public function page() {
    $viewBuilder = $this->entityTypeManager()->getViewBuilder('node');
    $nodeStorage = $this->entityTypeManager()->getStorage('node');

    // Render slider.
    $entitiesView = [];
    $cacheTags = [
      HomepageSettingsForm::CACHE_TAG
    ];

    $settings = $this->config('custom_homepage.settings');
    $sliderItems = $settings->get('slider') ?? [];
    foreach ($sliderItems as $item) {
      $entity = $nodeStorage->load($item['target_id']);
      $entitiesView[] = $viewBuilder->view($entity, 'slider');

      $cacheTags = Cache::mergeTags($cacheTags, $entity->getCacheTags());
    }

    $build = [
      'slider' => [
        '#theme' => 'slider',
        '#items' => $entitiesView,
        '#cache' => [
          'tags' => $cacheTags,
        ],
      ],
    ];

    // Render articles block by category.
    $tids = $this->entityQuery->get('taxonomy_term')
      ->condition('vid', 'categories')
      ->sort('weight', 'ASC')
      ->sort('name', 'ASC')
      ->execute();

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);

    foreach ($terms as $term) {
      $nids = $this->entityQuery->get('node')
        ->condition('type', 'article')
        ->condition('status', 1)
        ->condition('field_category', $term->id())
        ->sort('created', 'DESC')
        ->range(0, 3)
        ->execute();

      $entitiesView = [];
      foreach ($nids as $nid) {
        $entity = $nodeStorage->load($nid);

        $entitiesView[] = $viewBuilder->view($entity, 'teaser2');
      }

      $build[$term->id()] = [
        '#theme' => 'list_3_columns',
        '#label' => $term->label(),
        '#items' => $entitiesView,
      ];
    }

    return $build;
  }

}
