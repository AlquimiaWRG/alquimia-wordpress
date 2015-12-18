/**
 * @ngdoc     service
 * @name      alquimia.alquimia:WPApi
 * @requires  restangular
 * @author    Mauro Constantinescu <mauro.constantinescu@gmail.com>
 * @copyright © 2015 White, Red & Green Digital S.r.l.
 *
 * @description
 * Allows to easily communicate with the Wordpress' **WP REST API** plugin and specifically with the
 * Alquimia Wordpress plugin for the API.
 */
module.exports = [function WPApiProvider() {
  let $q, $http, url;

  /**
   * @ngdoc    method
   * @name     WPApi
   * @methodOf alquimia.alquimia:WPApi
   *
   * @param    {String} endpoint
   * The API endpoint name. This is defined by the API URL entry point fragment. For example, for the default "posts"
   * endpoint, handled by `WP_JSON_Posts`, the URL is `http://your.domain.com/wordpress/wp-json/posts` and the
   * `endpoint` parameter is "posts"
   *
   * @param    {Object} config
   * A configuration object. It can contain two keys that defines the WPApi instance behaviour:
   * - `filters`: it can contain up to three objects, with keys `defaults`, `items` and `item`. The properties of these
   *   three object can be atomic values or functions and are used to send GET parameters out to the API. For example:
   *
   *   ```
   *   new WPApi('posts', {
   *     filters: {
   *       defaults: {
   *         lang: function() { return getCurrentLanguage(); }
   *       },
   *       items: {
   *         posts_per_page: -1
   *       },
   *       item: {}
   *     }
   *   });
   *   ```
   *
   *   Means: send a `filter[lang]` parameter for every request, executing the `getCurrentLanguage` function and
   *   sending the returned value. When requesting all items (sending a request to `posts`), disable the pagination
   *   through a `filter[posts_per_page]=-1` parameter.
   *
   *   You can add filter when requesting a single item (`posts/post-slug`) too, putting something into the `item` key.
   * - `transform`: a function used to transform each item returned from the API before they are cached and returned.
   *   It is called with the item and the response headers as the arguments:
   *
   *   Example:
   *
   *   ```
   *   new WPApi('posts', {
   *     transform: function(item, headers) {
   *       item.totalPages = headers['x-wp-totalpages'];
   *       item.totalPosts = headers['x-wp-total'];
   *       return item;
   *     }
   *   });
   *   ```
   *
   *   Remember to return the item!
   *
   * @returns  {Object}
   * A `WPApi` instance mapped to the provided `endpoint` and configured to send `filters` and `transform`
   * the responses.
   *
   * @description
   * `WPApi` constructor.
   */
  class WPApi {
    constructor(endpoint, config = { filters: { defaults: {}, items: {}, item: {} } }) {
      this.config = config;
      this.items = {};
      this.cache = {};
      this.valid = true;
      this.endpoint = endpoint.replace(/^\/?/, '/'); // Eventually add leading slash

      if (config.transform && angular.isFunction(config.transform)) {
        this.transform = config.transform;
      }
    }

    /**
     * @ngdoc    method
     * @name     getItems
     * @methodOf alquimia.alquimia:WPApi
     *
     * @param {Boolean} flush
     * If `false` and this method was called before, the network request is skipped and the items are taken from
     * cache. If `true`, the network request is sent even if cached items are available.
     *
     * @param {Object} filters
     * An optional set of additional filters to be sent along with the API request. Default filters
     * from `config.filters.defaults` are merged with and overridden by items filters from `config.filters.items`,
     * that are merged with and overridden by these filters.
     *
     * @param {Object} params
     * An optional set of additional parameters to be sent along the API request.
     * Properties of this object are passed outside the `filter[]` array.
     *
     * @returns {Promise}
     * A Javascript Promise. The `resolve` function is called with an `$http` response as the only argument.
     * The `reject` function is called with a `WP_Error` from WP REST API`, that contains a `code` and a `message`
     * keys.
     *
     * @description
     * Sends a request for getting all the items from the WP REST API endpoint.
     */
    getItems(flush, filters = {}, params = {}) {
      return $q((resolve, reject) => {
        filters = angular.extend({}, this.config.filters.defaults, this.config.filters.items, filters);
        filters = WPApi.parseFilters(filters);
        filters = angular.extend(filters, params);

        let cacheKey = WPApi.getCacheKey(filters);

        /* Items from cache */
        if (this.valid && !flush && this.cache[cacheKey]) {
          let res = [];

          for (let item of this.cache[cacheKey]) {
            res.unshift(this.items[item]);
          }

          resolve(res);
          return;
        }

        /* Items from the API */
        let endpointUrl = `${url}index.php?json_route=${this.endpoint}`;

        for (let i in filters) { endpointUrl += `&${i}=${filters[i]}`; }

        $http.get(endpointUrl).then(response => {
          let headers = response.headers();
          let res = [];
          this.cache[cacheKey] = [];

          for (let item of response.data) {
            let slug = item.slug;

            item.route = `${item.route}/${item.ID}`;

            if (this.config.transform) {
              item = this.config.transform(item, headers);
            }

            this.items[slug] = item;
            this.cache[cacheKey].unshift(slug);
            res.unshift(item);
          }

          this.valid = true;
          resolve(res, true);
        }, function() {
          reject.apply(this, arguments);
          return;
        });
      });
    }

    /**
     * @ngdoc    method
     * @name     getItem
     * @methodOf alquimia.alquimia:WPApi
     *
     * @param {String} slug
     * The post slug to be requested. Usually, it is taken directly from the
     * {@link alquimia.alquimia:WPApi#methods_getItems getItems} response.
     *
     * @param {Boolean} flush
     * If `false` and this method was called before, the network request is skipped and the items are taken from
     * cache. If `true`, the network request is sent even if cached items are available.
     *
     * @param {Object} filters
     * An optional set of additional filters to be sent along with the API request. Default filters
     * from `config.filters.defaults` are merged with and overridden by items filters from `config.filters.item`,
     * that are merged with and overridden by these filters.
     *
     * @param {Object} params
     * An optional set of additional parameters to be sent along the API request.
     * Properties of this object are passed outside the `filter[]` array.
     *
     * @returns {Promise}
     * A Javascript Promise. The `resolve` function is called with an `$http` response as the only argument.
     * The `reject` function is called with a `WP_Error` from WP REST API`, that contains a `code` and a `message`
     * keys.
     *
     * @description
     * Sends a request for getting one item from the WP REST API endpoint.
     */
    getItem(slug, flush, filters = {}, params = {}) {
      return $q((resolve, reject) => {
        if (!flush && this.items[slug]) {
          /* Item from cache */
          resolve(this.items[slug]);
          return;
        }

        /* Item from API */
        filters = angular.extend({}, this.config.filters.defaults, this.config.filters.item, filters);
        filters = WPApi.parseFilters(filters);
        filters = angular.extend(filters, params);

        let endpointUrl = `${url}index.php?json_route=${this.endpoint}/${slug}`;

        for (let i in filters) { endpointUrl += `&${i}=${filters[i]}`; }

        $http.get(endpointUrl).then(response => {
          let item = response.data;
          let headers = response.headers();

          if (this.config.transform) {
            item = this.config.transform(item, headers);
          }

          this.items[slug] = item;

          resolve(item);
        }, function(error) {
          reject.apply(this, arguments);
          return;
        });
      });
    }

    /**
     * @ngdoc    method
     * @name     invalidate
     * @methodOf alquimia.alquimia:WPApi
     *
     * @description
     * TODO: now that caching depends on filters, this method may be useless
     * Schedule the cache to be discarded on the next request. This is useful when you know that a request is
     * about to be sent, but the object that is going to send it doesn't know that the cache should be discarded.
     *
     * Let's say that your API can handle traslations and react to language inconsistencies, so if you request a
     * post that is in French, but your request has a GET parameter that says "German", it will return the German
     * post. You have a dropdown menu on your post page that lets a user pick a language.
     *
     * Now, let's say that a user lands on the French category page that shows all the posts within that category,
     * following a link a friend gave him, but he/she only speaks German. He/She changes language, and your
     * application does this:
     *
     * - Intentionally sends a request that asks for the French category with German language;
     * - picks the slug of the German translation;
     * - invalidates the WPApi cache;
     * - changes `$location` so the URL is consistent with the right category translation;
     * - asks for posts again.
     *
     * In this case, not invalidating the cache would have caused WPApi to serve the cached posts, because it
     * doesn't know that something changed. The post request couln't be done with `flush: true`, unless you
     * saved the last language before doing the request. This is why this method is useful.
     */
    invalidate() {
      valid = false;
    }

    static parseFilters(filters) {
      let ret = {};

      for (let i in filters) {
        if (angular.isFunction(filters[i])) {
          ret[`filter[${i}]`] = filters[i]();
        } else {
          ret[`filter[${i}]`] = filters[i];
        }
      }

      return ret;
    }

    static getCacheKey(filters) {
      let a = [];

      for (let i in filters) {
        a.push(i + filters[i]);
      }

      a.sort();
      return `qf_${a.join('')}`;
    }
  }

  this.$get = ['$q', '$http', (_$q, _$http) => {
    $q = _$q;
    $http = _$http;
    return WPApi;
  }];

  this.setBaseUrl = function(_url) {
    // Eventually add trailing slash
    url = _url.replace(/\/?$/, '/');
  }
}];
