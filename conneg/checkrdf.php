<?php
//DebugBreak();
/*
Accept: application/rdf+xml, application/xml, text/rdf
303 redirect to http://asn.jesandco.org/resources/D1000152/rdf.xml

Accept: application/json, text/json
303 redirect to http://asn.jesandco.org/resources/D1000152/rdf.json

Accept: application/turtle, application/x-turtle, text/turtle, text/plain
303 redirect to http://asn.jesandco.org/resources/D1000152/rdf.turtle

Accept: application/rdf+n3, text/n3, text/rdf+n3
303 redirect to http://asn.jesandco.org/resources/D1000152/rdf.n3

http://asn.jesandco.org/resources/D1000152/rdf (only)
Accept: application/xhtml+xml, text/html
303 redirect to http://asn.jesandco.org/resources/D1000152/rdf.html

*/

//****************************************
//for testing...
//****************************************

//require_once './includes/bootstrap.inc';
//drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

//accept headers
//$_SERVER['HTTP_ACCEPT'] = 'application/rdf+xml, application/xml, text/rdf';
//$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
//$_SERVER['HTTP_ACCEPT_CHARSET'] = 'ISO-8859-1,utf-8;q=0.7,*;q=0.7';
//$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-us,en;q=0.5';
//$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip,deflate';
//$_SERVER["HTTP_IF_MODIFIED_SINCE"] = 'Fri, 01 Apr 2009 00:11:33 GMT';

//uri
//$_SERVER['REQUEST_URI'] = 'http://asn.jesandco.org/resources/S102D7CC_taxon.xml';
//$_SERVER['REQUEST_URI'] = 'http://asn.jesandco.org/resources/D1000152';
//$_SERVER['REQUEST_URI'] = 'http://asn.jesandco.org/resources/S10059CF/en/taxon/rdf.xml';
//$_SERVER['REQUEST_URI'] = 'http://asn.jesandco.org/taxon/rdf/resources/S10059CF';
//$_SERVER['REQUEST_URI'] = 'http://asn.jesandco.org/resources/S10059CF';
//$_SERVER['REQUEST_URI'] = 'http://asn.jesandco.org/resources/S10059CF/rdf.ttl';

//$base_root = "http://asn/jesandco.org";

//****************************************
//...for testing
//****************************************


/**
* callback function to lowercase an array
*
* @param  string $value
* @param  string $key
*/
function lc(&$value, $key)
{
  $value = strtolower($value);
}

//we're here because there's no file to serve

//get the request URI
$uri = request_uri();
$uri = preg_replace('%^/http://%', 'http://', $uri);
$url = parse_url($uri);
$path = pathinfo($url['path']);
$pathParts = explode("/",ltrim($path['dirname'],"/"));
$fileParts = array();
array_walk($pathParts, "lc");

//if the filename contains an underscore, then it's been processed (or hacked) and we need the real filename
if (false !== strpos($path['filename'],"_"))
{
  $fileParts = explode("_", $path['filename']);
  //the actual filename MUST be the first part of the file
  $path['filename'] = $fileParts[0];
  //this strips out anything between the first underscore and the file extension
  $path['basename'] = preg_replace('/(.*?)_.*(\..*)/', '$1$2', $path['basename']);
}

switch (strtolower($pathParts[0]))
{
  case "resources":
    //FIXME: No it can't. We need to change this to only accept addons after the resource
    //a uri can have any number of directives between 'resources' and 'filename'

    //$basePath should always be 'resource/id' or the request is malformed
    $basePath = $pathParts[0] . "/" . $path['filename'];
    $taxon = in_array('taxon', $pathParts) || in_array('taxon', $fileParts);
    $manifest = in_array('manifest', $pathParts) || in_array('manifest', $fileParts);
    $rdf = in_array('rdf', $pathParts) || in_array('rdf', $fileParts);
    $full = in_array('full', $pathParts) || in_array('full', $fileParts);
    //$baseUri = $url['scheme'] . '://' . $url['host'] . "/" . $basePath; //FOR TESTING
    $baseUri = $base_url . "/" . $basePath;

    //Does the filename contain an underscore
    $addon = '';
    if (!count($fileParts) && !count($fileParts) && count($pathParts) > 1)
    {
      $addon = "_" . $pathParts[1];
    }

    /*
    * TODO: Provide an array of acceptable language filters for inclusion in URI
    foreach($languageArray as $language)
    {
    if (in_array($language, $pathParts)) break;
    }
    * this will also have to handle cases like 'en-GB' and 'en-US' where someone has requested just 'en'
    */

    if ($manifest)
    {
      //TODO: execute the same cache and if-modified functionality as RDF
      $fileName = rtrim($jsonPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path['basename'];
      $splFile = new SplFileInfo($fileName);
      $lastMod = $splFile->getMTime();
      ob_start('ob_gzhandler');
      //build the headers
      //set the last-modified
      header("Content-Type: application/json;charset=utf-8");
      header("Vary: Accept");
      header("Content-Location: " . $uri);
      header("last-modified: " . gmdate("D, d M Y H:i:s", $lastMod) . " GMT");
      //set max-age to 2 days
      header("Cache-Control: max-age=172800, public, must-revalidate");
      header('Content-Length: ' . $splFile->getSize());
      //spit it out
      echo file_get_contents($fileName);
      ob_end_flush();
      exit();

    }

    //if there's a file extension we skip this and head straight for building
    //but what if there's no file extension or now '/rdf/'?
    if (!isset($path['extension']))
    {
      //add the conneg
      require_once 'omrConneg_class.php';
      //we have to get the best variant based on the request
      $variant = omrConneg::getPreferredVariant();

      //it's not html, so we tack some request-specific info onto the uri and redirect
      if ('html' != $variant)
      {
        $uri = $base_url . $uri;
        $uri .= $addon . "." . $variant;

        //do a redirect
        header("HTTP/1.1 303 See Other");
        header("Location: $uri");
        exit();
      }
    }
    else if($addon)
      {
        $uri = $base_url . "/resources/" . $path['filename'] . $addon . "." . $path['extension'];
        //do a redirect
        header("HTTP/1.1 303 See Other");
        header("Location: $uri");
        exit();
      }
      else if('html' != $path['extension'])
        {
          require 'create_rdf.php';
        }

        break;

  case "api":
    if ("1" == $pathParts[1])
    {
      require_once 'create_ws.php';
    }

  case "category":
    //check the filters
    //is it a topic?
/*
    if ('asntopic' == strtolower($pathParts[1]))
    {
      if (!empty($path['filename']))
      {
        $nids = array();
        $result = db_query_range(db_rewrite_sql('SELECT n.nid, n.created FROM {node} n WHERE n.promote = 1 AND n.status = 1 ORDER BY n.created DESC'), 0, variable_get('feed_default_items', 10));

        while ($row = db_fetch_object($result))
        {
          $nids[] = $row->nid;
        }

      }
      $sql = 'foo';
    }
*/
    //get the data
    //build the file
    //is it xml?
    //is it rdf?
    //what's the serialization?

    break;
  default:
}

function request_uri() {

  if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
  }
  else {
    if (isset($_SERVER['argv'])) {
      $uri = $_SERVER['SCRIPT_NAME'] .'?'. $_SERVER['argv'][0];
    }
    elseif (isset($_SERVER['QUERY_STRING'])) {
      $uri = $_SERVER['SCRIPT_NAME'] .'?'. $_SERVER['QUERY_STRING'];
    }
    else {
      $uri = $_SERVER['SCRIPT_NAME'];
    }
  }
  // Prevent multiple slashes to avoid cross site requests via the FAPI.
  $uri = '/'. ltrim($uri, '/');

  return $uri;
}

