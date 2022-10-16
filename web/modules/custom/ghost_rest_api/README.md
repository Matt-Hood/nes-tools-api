## How to use the module

The module's name is `search_api_json` so that it can potentially grow into a
contrib module, but this may need to be namespaced to avoid conflicts if another
module is created first. To use the module, enable it with drush and rebuild
the cache.

This module adds a custom controller that adds the following route:

```
/api/search-results/{search_keys}?page=0&sort=date
```

Search keys and query parameters are passed through XSS filtering, and if the
page/sort params are not within the expected values, they'll fall back to ]
default values. Search keys can be hyphenated or encoded (e.g `foo%20bar`). If
no search key argument is passed, no results will be returned.

There is an optional query parameter called type that can be passed with the
image value to return results that have a thumbnail. Otherwise, all results
are returned.

```
/api/search-results/{search_keys}?page=0&sort=date&type=image
```

## Notes on the logic

The module will attempt to load a search API index with the name `local`, and if
that's not enabled, it will load `acquia_search_index` instead.

The response returned by the endpoint is a CacheableJsonResponse() that includes
the following cache metadata:

- **Contexts**
    - `url.query_args:page`
    - `url.query_args:sort`
    - `url.query_args:type`
- **Tags**
    - `search_api_json`
    - `node:{nid}` (retrieved via `$entity->getCacheTags()` and merged into the
      tags array)

## Search results

Search results are returned with the following format. If any of the pager
options are invalid (e.g. previous page when on the first page), that property
will be set to false. Additionally, there is a `message` property in the results
that will be `false` if results were found, or will return a translated message
string explaining why no results were found. The type property will return a
string value if the image value is passed in, otherwise it will result in
`false`.

Media thumbnails are pre-retrieved to include data relevant to the front end
including image URL (for the image style), title, alt, width, and height (both
for the image style dimensions, not the original image).

```json
{
    "message": false,
    "total_count": 80,
    "search_keys": "moral",
    "sort": "relevance",
    "type": false,
    "pager": {
        "pages": [
            0,
            1,
            2,
            3,
            4,
            5,
            6,
            7
        ],
        "current": 0,
        "first": false,
        "prev": false,
        "next": 1,
        "last": 7
    },
    "content": [
        {
            "title": "Moral Literacy",
            "path": "/research/story/moral-literacy",
            "created": "2003-05-01 00:00:00",
            "excerpt": "… <strong>highlighted</strong> search phrase …",
            "media": {
                "uri": "https://example.com/path/to/image/style.jpg",
                "title": "",
                "alt": "test",
                "width": 500,
                "height": 375
            }
        },
        {
            "title": "Bonhoeffer’s Dilemma",
            "path": "/research/story/bonhoeffers-dilemma",
            "created": "2000-05-01 00:00:00",
            "excerpt": "… <strong>highlighted</strong> search phrase …",
            "media": null
        },
        {
            "title": "Know the Rules",
            "path": "/research/story/know-rules",
            "created": "2002-01-01 00:00:00",
            "excerpt": "… <strong>highlighted</strong> search phrase …",
            "media": null
        }
    ]
}
```

## Areas for improvement

- Replace the image style with whatever image style machine name the FE team
  needs it to be.
- Return additional image URLs if needed (e.g. responsive images).
- Refactor the image to use a computed field.
- Add a settings form and default config to allow more customizable settings
  related to fields used and the search index that's queried.
