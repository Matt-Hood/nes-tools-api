<?php

namespace Drupal\search_api_json\Search;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Define a service used to query Search API indexes.
 */
class SearchService {

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * SearchService constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager) {

    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get a search index for a particular search.
   *
   * @param string $type
   *   Type of search.
   *
   * @return bool|\Drupal\search_api\IndexInterface
   *   The search index.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSearchIndex($type = 'local') {
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $index_storage->load($type);

    // See if Apache Solr is enabled first.
    if ($index && !$index->isServerEnabled()) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $index_storage->load('acquia_search_index');

      // See if Acquia Search is enabled.
      if (!$index->isServerEnabled()) {
        $content['message'] = t('Search is disabled at this time.');
        return FALSE;
      }
    }

    return $index;
  }

  /**
   * Get an indexed search query object.
   *
   * @param \Drupal\search_api\IndexInterface|null $index
   *   (Optional) The search index. Will be fetched if not passed in.
   * @param string $type
   *   (Optional) Type of search.
   *
   * @return bool|\Drupal\search_api\Query\QueryInterface
   *   The query object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getIndexedSearchQuery($index = NULL, $type = 'local') {
    if (empty($index)) {
      $index = $this->getSearchIndex($type);
    }

    if (!empty($index)) {
      // Get a query object.
      $query = $index->query();

      // Set the language on it.
      $language = $this->languageManager->getCurrentLanguage()->getId();
      $query->setLanguages([$language]);

      return $query;
    }
    else {
      return FALSE;
    }
  }

}
