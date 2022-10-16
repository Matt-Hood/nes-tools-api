<?php

namespace Drupal\search_api_json\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\search_api_json\Search\SearchService;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\search_api\IndexInterface;

/**
 * Returns responses for API Search Page routes.
 */
class APISearchAllController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The search service.
   *
   * @var \Drupal\search_api_json\Search\SearchService
   */
  protected $searchService;

  /**
   * The entity type manager.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an APISearchAllController object.
   *
   * @param \Drupal\search_api_json\Search\SearchService $search_service
   *   The search service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(
    SearchService $search_service,
    RequestStack $request_stack
  ) {
    $this->searchService = $search_service;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('search_api_json.search_service'),
      $container->get('request_stack')
    );
  }

  /**
   * Generates content for the Search All feature.
   *
   * Search URL pattern: "/api/search-results/{search-term}?page=0&sort=date".
   * Use "&type=image" as an optional query parameter.
   *
   * @param string $search_keys
   *   Search text.
   *
   * @return string
   *   The rendered Search All response.
   */
  public function searchResponse($search_keys = '') {
    $search_keys = Xss::filter($search_keys);

    // Get pager and sort query parameters.
    $request = $this->requestStack->getCurrentRequest();
    $query_params = $request->query->all();

    $page = isset($query_params['page']) ? Xss::filter($query_params['page']) : 0;
    $sort = isset($query_params['sort']) ? Xss::filter($query_params['sort']) : 'relevance';
    $type = isset($query_params['type']) ? Xss::filter($query_params['type']) : FALSE;

    // Force clean parameters.
    $page = intval($page);
    if ($sort !== 'date') {
      $sort = 'relevance';
    }
    if ($type !== 'image') {
      $type = FALSE;
    }

    // Build fallback response.
    $output = [
      'message' => FALSE,
      'content' => [],
      'total_count' => 0,
      'search_keys' => $search_keys,
      'sort' => $sort,
      'pager' => $this->getPager(0, 0, 10),
    ];

    // Add cache contexts for query parameters.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheContexts([
      'url.query_args:page',
      'url.query_args:sort',
      'url.query_args:type',
    ]);
    $cache_tags = ['search_api_json'];

    // Retreive either the local search index, or Acquia's search index.
    $index = $this->searchService->getSearchIndex();

    // Get the search query, if search is enabled.
    if (!empty($index)) {
      $query = $this->searchService->getIndexedSearchQuery($index);
      $query->addCondition('status', 1);
    }
    // Otherwise, exit early.
    else {
      $output['message'] = $this->t('Search index is unavailable.');
      $response = new CacheableJsonResponse($output);
      $response->addCacheableDependency($cache_metadata);
      return $response;
    }

    if ($search_keys == '') {
      $output['message'] = $this->t('No search terms were supplied.');
      $response = new CacheableJsonResponse($output);
      $response->addCacheableDependency($cache_metadata);

      return $response;
    }

    // If a search key was provided, add it as a condition to the query.
    $query->keys($search_keys);

    // Adjust the query to account for the pager.
    $count = 10;
    $page_start = $page * $count;
    $query->range($page_start, $count);

    // Search is by relevance by default, so we only need to change this if
    // date is the sort order.
    if ($sort == 'date') {
      $query->sort('created', 'DESC');
    }

    // If type is image, add condition to query to filter by results that
    // have a thumbnail.
    if ($type == 'image') {
      $query->addCondition('thumbnail', NULL, '<>');
    }

    // Execute the query.
    $results = $query->execute();
    $total_count = $results->getResultCount();

    // Build the pager.
    $pager = $this->getPager($page, $total_count, $count);
    $output['pager'] = $pager;

    // Exit early if there were no results.
    if (!$total_count) {
      $output['message'] = $this->t('No results found for @keys.', ['@keys' => $search_keys]);
      $response = new CacheableJsonResponse($output);
      $response->addCacheableDependency($cache_metadata);

      return $response;
    }

    // Get results.
    $results_array = $results->getResultItems();
    $results_content = $this->getResultsContent($results_array, $index);
    $cache_tags = array_merge($cache_tags, $results_content['cache_tags']);

    // Prepare output markup.
    $output = [
      'message' => FALSE,
      'total_count' => $total_count,
      'search_keys' => $search_keys,
      'sort' => $sort,
      'type' => $type,
      'pager' => $pager,
      'content' => $results_content['content'],
    ];

    // Add cache tags to our metadata for all nodes in the loop.
    $cache_metadata->addCacheTags($cache_tags);

    // Build and return the cacheable response.
    $response = new CacheableJsonResponse($output);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Iterates over query result and builds content array.
   *
   * This method is used to take query results returned by the search API
   * index and iterate over them, building up a simplified array of content
   * relevant to our API endpoint.
   *
   * @param array $results_array
   *   Array of results returned by the Search API database query.
   * @param \Drupal\search_api\IndexInterface $index
   *   Search API index used for the query.
   *
   * @return array
   *   An array with a 'content' key for results and 'cache_tags' key for
   *   relevant cache tags.
   */
  private function getResultsContent(array $results_array, IndexInterface $index) {
    $result_objects = $index->loadItemsMultiple(array_keys($results_array));

    $content = [];
    $cache_tags = [];

    foreach ($result_objects as $key => $item) {
      // Retrieve the node object.
      /** @var \Drupal\Core\Field\FieldItemInterface  $item */
      $entity = $item->getEntity();
      // Prepare results for this row.
      $content[] = $this->getResult($entity, $results_array[$key]);
      $cache_tags = array_merge($cache_tags, $entity->getCacheTags());
    }

    // Append the returned cache tags for this node into the cache tags array.
    return [
      'content' => $content,
      'cache_tags' => $cache_tags,
    ];
  }

  /**
   * Retrieve result content for an entity.
   *
   * Retrieve a single result array for a given entity in the Search API
   * query results array.
   *
   * @param object $entity
   *   The node or other entity for this search result.
   * @param object $result_item
   *   The Search API result item for this entity, used for accessing data
   *   specific to Search API, such as the relevant excerpt.
   *
   * @return array
   *   Array of result data with title, path, created, excerpt, and media keys.
   */
  private function getResult($entity, $result_item) {
    $media = $this->getThumbnail($entity);

    // Append to the content array.
    return [
      'title' => $entity->label(),
      'path' => \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $entity->id()),
      'created' => DrupalDateTime::createFromTimestamp($entity->created->value)->format('Y-m-d H:i:s'),
      'excerpt' => $result_item->getExcerpt(),
      'media' => $media,
    ];
  }

  /**
   * Retrieve a pager array.
   *
   * Because the pager is not directly available on the query, this method is
   * used to build an array of pager content useful for the front-end to
   * construct a pager at render time.
   *
   * @param int $page
   *   Page (0-indexed).
   * @param int $total_count
   *   Total number of results to make a pager for.
   * @param int $count
   *   (Optional) Total number of results per page (defaults to 10, minimum 1).
   *
   * @return array
   *   Array with the keys pages, first, prev, current, next, last.
   */
  private function getPager($page, $total_count, $count = 10) {
    $pages = [];

    if ($total_count > 0) {
      $page_count = ceil($total_count / max($count, 1));
      for ($i = 0; $i < $page_count; $i++) {
        $pages[$i] = $i;
      }
    }

    // Build the pager.
    $pager = [
      'pages' => $pages,
      'current' => $page,
    ];

    // Handle first page and previous page.
    if (isset($pages[$page - 1])) {
      $pager['first'] = array_key_first($pages);
      $pager['prev'] = $page - 1;
    }
    else {
      $pager['first'] = FALSE;
      $pager['prev'] = FALSE;
    }

    // Handle next page and last page.
    if (isset($pages[$page + 1])) {
      $pager['next'] = $page + 1;
      $pager['last'] = array_key_last($pages);
    }
    else {
      $pager['next'] = FALSE;
      $pager['last'] = FALSE;
    }

    return $pager;
  }

  /**
   * Retrieve thumbnail image for an entity.
   *
   * Retrieves a thumbnail image either directly from the entity or from a
   * hero paragraph on the entity.
   *
   * @param object $entity
   *   The node or other entity to retrieve the thumbnail for.
   * @param string $style_name
   *   Machine name of the image style to use for the returned thumbnail.
   *
   * @return array
   *   Array with the keys uri, title, alt, width, and height.
   */
  public function getThumbnail($entity, $style_name = '16_9_1000w') {
    // Determine if this node has an image. Grab the media entity if so.
    $media = NULL;
    $image_result = NULL;

    // Load the computed thumbnail (see psu_images custom module).
    if ($media = $entity->get('thumbnail')->referencedEntities()) {
      $media = reset($media);
      $files = NULL;
      $file = NULL;

      // Retrieve the appropriate image.
      if (!$media->get('field_image')->isEmpty()) {
        $files = $media->get('field_image')->referencedEntities();
      }
      else {
        $files = $media->get('thumbnail')->referencedEntities();
      }
      if ($files && $style = ImageStyle::load($style_name)) {
        $file = reset($files);

        if ($file) {
          // Build the URL to the image.
          $image_uri = $file->getFileUri();

          // Retrieve the height/width of the rendered image style.
          $style_uri = $style->buildUri($image_uri);
          $image_factory = \Drupal::service('image.factory')->get($style_uri);
          $height = $image_factory->getToolkit()->getHeight();
          $width = $image_factory->getToolkit()->getWidth();

          // Put all of that into an array for use later.
          $image_result = [
            'uri' => $file ? $style->buildUrl($image_uri) : NULL,
            'title' => $media->field_image->title,
            'alt' => $media->field_image->alt,
            'width' => $width,
            'height' => $height,
          ];
        }
      }
    }

    return $image_result;
  }

}
