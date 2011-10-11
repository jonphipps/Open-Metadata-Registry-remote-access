<?php

  //add content negotiation
  require_once 'PHP5.x/conNeg.inc.php';

  class omrConneg {

    public static
    $supportedTypes= array(
    array('html', 1.00, 'application/xhtml+xml', "application"),
    array('html', 0.99, 'text/html'            , "text"),
    array('xml',  0.60, 'application/xml'      , "application"),
    array('xml',  0.50, 'text/xml'             , "text"),
    array('rdf',  0.90, 'application/rdf+xml'  , "application"),
    array('rdf',  0.60, 'application/xml'      , ""),
    array('rdf',  0.50, 'text/rdf'             , "text"),
    array('n3' ,  0.60, 'text/rdf+n3'          , "text"),
    array('n3' ,  0.50, 'text/n3'              , ""),
    array('n3' ,  0.90, 'application/rdf+n3'   , "application"),
    array('ttl',  0.50, 'text/plain'           , ""),
    array('ttl',  0.60, 'text/turtle'          , "text"),
    array('ttl',  0.70, 'text/x-turtle'        , ""),
    array('ttl',  0.90, 'application/turtle'   , "application"),
    array('ttl',  0.40, 'application/x-turtle' , ""),
    array('json', 0.90, 'application/json'     , "application"),
    array('json', 0.80, 'text/json'            , "text"),
    );

    private static
    $isNegotiated = FALSE,
    $variant,
    $cacheSetting;


    /**
    * Checks the Accept HTTP header to determine the best
    * variant; checks wether a page is cached for that variant's
    * URI; returns it and dies if a cached page was found.
    * This is typically called from hook_init().
    */
    private static function cacheStart() {

      self::$variant = self::getPreferredVariant();

      $cacheMode = variable_get('cache', CACHE_DISABLED);
      $cache = '';
      if ($cacheMode != CACHE_DISABLED) {
        // An output buffer for the cache has probably already been
        // set up during bootstrap; close it, because we will start
        // a new one
        if (page_get_cache(TRUE)) {
          ob_end_clean();
        }
        // temporarily change REQUEST_URI to the variant's URI; this
        // will cause page_get_cache to check the cache for that URI
        $temp = $_SERVER['REQUEST_URI'];
        $_SERVER['REQUEST_URI'] = self::getVariantUri(self::$variant, FALSE);
        $cache = page_get_cache();
        $_SERVER['REQUEST_URI'] = $temp;
      }

      // Not yet cached? Just return and process as usual
      if (!$cache) return;

      // It's a negotiated response
      self::setHeaders();
      // Output the cached body and headers
      drupal_page_cache_header($cache);

      // Try to properly shut down Drupal before exiting. This is not quite
      // complete because other modules may have been already initialized.
      bootstrap_invoke_all('exit');

    }

    /**
    * Invokes one of several handlers, depending on the variant
    * chosen by the content negotiation logic.
    *
    * @param $node
    *   A node to be passed to the handler
    * @param $variant_handlers
    *   Associative array with variant names as keys and private static function
    *   names as values
    * @return
    *   The result of invoking the handler appropriate for the
    *   best variant
    */
    private static function doConneg(&$node, $variant_handlers) {
      self::setIsNegotiated();
      if (!isset($variant_handlers[self::$variant])) {
        drupal_set_message(t("Internal error: Preferred variant '%variant' has no handler", array('%variant' => self::$variant)), 'error');
        return;
      }
      return $variant_handlers[self::$variant]($node);
    }


    /**
    * Returns the URI of the current page, with a variant file extension
    * appended, such as 'rdf' or 'html'.
    *
    * @param $variant
    *   A variant name, such as 'rdf' or 'html'
    * @param $absolute
    *   If TRUE, an absolute URI will be returned. If false, the REQUEST_URI for the variant will be returned.
    * @return
    *   The URI of the specified variant
    */
    public static function getVariantUri($variant, $absolute = TRUE) {
      // We use $_REQUEST instead of $_GET because we want the original
      // URI, without alias rewriting
      $path = $absolute ? (isset($_REQUEST['q']) ? $_REQUEST['q'] : '<front>') : request_uri();
      if (substr($path, -1) == '/') {
        $path .= $variant;
      } else {
        $path .= '.' . $variant;
      }
      $path = ltrim($path, '/');
      return $absolute ? url($path, array('absolute' => TRUE)) : $path;
    }

    /**
    * Marks this page as being subject to negotiation. This will
    * switch off the standard page cache.
    */
    private static function setIsNegotiated() {
      self::$isNegotiated = TRUE;
      // store cache settings so we can restore them later
      self::$cacheSetting = $GLOBALS['conf']['cache'];
      // caching off
      $GLOBALS['conf']['cache'] = FALSE;
      self::setHeaders();
    }

    /**
    * Checks if the current page has been marked as negotiated, and if it is,
    * then it will be stored in the cache under the variant's URI.
    * This is typically called from hook_exit().
    */
    private static function cacheEnd() {
      if (!self::$isNegotiated) return;
      // restore original cache settings
      $GLOBALS['conf']['cache'] = self::$cacheSetting;

      // temporarily change REQUEST_URI to trick page_set_cache into storing
      // the page at the variant URI
      $temp = $_SERVER['REQUEST_URI'];
      $_SERVER['REQUEST_URI'] = self::getVariantUri(self::$variant, FALSE);
      if (variable_get('cache', CACHE_DISABLED) != CACHE_DISABLED) {
        // store page in cache
        page_set_cache();
      }
      $_SERVER['REQUEST_URI'] = $temp;
    }

    /**
    * Sets the Vary and Content-Location HTTP headers. Must be invoked for
    * any response that is subject to negotiation, including cached responses.
    */
    private static function setHeaders() {
      drupal_set_header("Vary: Accept");
      drupal_set_header("Content-Location: " . self::getVariantUri(self::$variant, TRUE));
    }


    /**
    * Gets the best mime type for the request
    * matches the Accept HTTP header sent by the client.
    *
    * @return string
    *   A mime type
    */
    public static function getBestMime() {

      $appTypes = array('type' => array(), 'qFactorApp' => array());
      foreach (self::$supportedTypes as $type) {
        $appTypes['type'][] = $type[2];
        $appTypes['qFactorApp'][] = $type[1];
      }

      return conNeg::mimeBest($appTypes);
    }

    /**
    * Gets the best mime type for the request
    * matches the file extension sent by the client.
    *
    * @param $variant
    *   A variant name, such as 'rdf' or 'html'
    * @param $format
    *   Either 'text' or 'application'
    * @return string
    *   A mime type
    */
    public static function getExtensionMime($variant, $format) {

      foreach (self::$supportedTypes as $type) {

        if ($variant == $type[0] && $format == $type[3])
        {
          return $type[2];
        }
      }

      return false;

    }
    /**
    * Computes the variant name ('rdf', 'html', etc.) that best
    * matches the Accept HTTP header sent by the client.
    *
    * @return
    *   A variant name such as 'html', 'rdf'
    */
    public static function getPreferredVariant() {

      $variant = 'rdf';

      $best = self::getBestMime();

      foreach (self::$supportedTypes as $type) {
        if ($type[2] == $best) {
          $variant = $type[0];
        }
      }
      return $variant;
    }
  }
?>
