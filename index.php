<?php
//debugbreak();

//****************************************
//for testing...
//****************************************

//accept headers
//$_SERVER['HTTP_ACCEPT'] = 'application/rdf+xml, application/xml, text/rdf';
//$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
//$_SERVER['HTTP_ACCEPT_CHARSET'] = 'ISO-8859-1,utf-8;q=0.7,*;q=0.7';
//$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-us,en;q=0.5';
//$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip,deflate';
//$_SERVER["HTTP_IF_MODIFIED_SINCE"] = 'Fri, 01 Apr 2009 00:11:33 GMT';

//uri
//$_SERVER['REQUEST_URI'] = '/RDARelationshipsWEMI.rdf';
//$_SERVER['REQUEST_URI'] = '/termLIst/frequency/1007.rdf';
//$_SERVER['REQUEST_URI'] = '/termLIst/frequency/1007';

//****************************************
//...for testing
//****************************************


//setup
//$apiUrl = "http://50.56.57.42/api/";
$registryUrl= "http://metadataregistry.org";
$apiUrl = $registryUrl . "/api/";
//$apiUrl = "http://registry:81/api/";
$domain = "http://rdvocab.info";
$useFopen = false;
//time between update checks
$updateInterval = 300; // 5 minutes * 60 seconds
$dataFolder = "data";
$cacheFolder = "cache";
//force reload of main cache from browser
$forceReload = isset($_GET['forcereload']);

//load schema/vocab data from file
$data['schemas'] = getLocalData("schemas");
$data['vocabs']  = getLocalData("vocabs");

//special handler for hash URIs, replaces only the first instance of hash token with hash
$_SERVER['REQUEST_URI'] = preg_replace('/%23(.+)/', '#$1', $_SERVER['REQUEST_URI']);
//if the request is for vocab/schema rdf
$_SERVER['REQUEST_URI'] = rtrim($_SERVER['REQUEST_URI'], "/#");

$urlParsed = parse_url($_SERVER['REQUEST_URI']);
if (!empty($urlParsed['path'])) //we can skip everything else and display html, at least in this domain
{
  //@todo these could be moved to a one-time install script
  if (!is_dir($dataFolder))
  {
    mkdir($dataFolder);
  }
  if (!is_dir($cacheFolder))
  {
    mkdir($cacheFolder);
  }
  $path = pathinfo($urlParsed['path']);
  //if there's a hash URI, then we have to look for the extension
  if (isset($urlParsed['fragment']))
  {
    $fragParts = explode(".", $urlParsed['fragment']);
    if (isset($fragParts[1]))
    {
    $path['extension'] = preg_replace('/\?.*$/', '', $fragParts[1]);
    }
  }
	if (DIRECTORY_SEPARATOR == $path['dirname'])
	{
		$path['dirname'] = '';
	}
  $pathParts = explode("/", ltrim($path['dirname'],"/"));
  //if it's a fragment write it in the cache folder
  $writeFolder = (count($pathParts) <= 1) ? $dataFolder : $cacheFolder ;
$url = strtolower($domain . $_SERVER['REQUEST_URI']);
  $uri = strtolower($domain . $path['dirname'] . "/" . $path['filename']);
  $filePath = $writeFolder . $path['dirname'] . DIRECTORY_SEPARATOR . $path['filename'];


//is it a legit uri? (not sure yet)
//check the class, based solely on pathparts count
	$class='';
	$testUri = strtolower($domain . $path['dirname'] . "/" . $path['filename']);
	if (array_key_exists($testUri, $data['schemas']))
  {
    $class = "schema";
      $touchTime = $data['schemas'][$uri]['lastUpdate'];
  }
	if (array_key_exists($testUri, $data['vocabs']))
  {
    $class = "concept_scheme";
      $touchTime = $data['vocabs'][$uri]['lastUpdate'];
  }
	/**
	* @todo this has to be tested against marc21rdf hash URIs, since this doesn't appear to be generic
	**/
  $testUri = strtolower($domain . $path['dirname']);
    if (array_key_exists($testUri, $data['schemas']))
  {
    $class = "schema_property";
      $touchTime = $data['schemas'][$testUri]['lastUpdate'];
  }
  if (array_key_exists($testUri, $data['vocabs']))
  {
    $class = "concept";
      $touchTime = $data['vocabs'][$testUri]['lastUpdate'];
  }
}

if ($class)
{
  if (!isset($path['extension']))
  {
    //we do conneg to get one
    require_once './conneg/omrConneg_class.php';
    //we have to get the best variant based on the request
    $path['extension'] = omrConneg::getPreferredVariant();
    //and redirect if not html
    if (in_array($path['extension'], array('rdf', 'xsd')))
    {
      header("Location: " . $_SERVER['REQUEST_URI'] . "." . $path['extension'], true, 303);
      exit();
    }
  }

    if (in_array($path['extension'], array('rdf', 'xsd')))
    {
  $apiUrl .= "get?class=" . $class;
  $apiUrl .= "&type=" . $path['extension'];
  $apiUrl .= "&uri=" . rawurlencode($uri);

			$filePath .=  "." . $path['extension'];
    //check for the dir and create if needed
      if (!is_dir($writeFolder . DIRECTORY_SEPARATOR . $pathParts[0]))
    {
        mkdir($writeFolder . DIRECTORY_SEPARATOR . $pathParts[0]);
    }
      if (!is_dir($writeFolder . $path['dirname']))
    {
        mkdir($writeFolder . $path['dirname']);
    }

    //check for the local file and create if needed
      //$fileExists = file_exists($filePath);

      if (!file_exists($filePath) || (filemtime($filePath) < $touchTime)) //get the rdf from the server
    {
      $file = getData($apiUrl);
      if ($file)
      {
        $success = file_put_contents($filePath, $file);
        touch($filePath, $touchTime);
          //$file = file_get_contents($filePath);
      }
    }
    else
    {
      $file = file_get_contents($filePath);
    }

    if ($file)
    {
      //set the correct header
      $rdf = ('rdf' == $path['extension']) ? "rdf+" : '';
      //ob_start('ob_gzhandler');
      header("Content-Type: application/" . $rdf . "xml; charset=utf-8");
      header("Vary: Accept");
      header("Content-Location: " . $url);
      header("last-modified: " . gmdate("D, d M Y H:i:s", filemtime($filePath)) . " GMT");
      //set max-age to 2 days
      header("Cache-Control: max-age=172800, public, must-revalidate");
      header('Content-Length: ' . filesize($filePath));
      echo $file;
      //ob_end_flush();

    }
    else
    {
      //debug_print_backtrace();
      header(' ', true, 404);
    }

    exit();
  }

  //html requests get redirected to registry
  if ('html' == $path['extension'])
  {
      //if there's a fragment, we have to look it up as the next level down
      $part = '';
      if (isset($fragParts[0]))
      {
        $uri .= "#" . $fragParts[0];
        if ("concept_scheme" == $class) $class = "concept" ;
        if ("schema" == $class) $class = "schema_property" ;
      }
      $apiUrl .= "get?class=" . $class;
      $apiUrl .= "&type=" . $path['extension'];
      $apiUrl .= "&uri=" . rawurlencode($uri);
    $url = getData($apiUrl);
    //this should return just redirect the URL to the registry
    if ($url)
    {
      header("Location: " . $url, true, 303);
    }
    else
    {
      //debug_print_backtrace();
      header(' ', true, 404);
    }

    exit();
  }
}
}

//else display the local data as html
require_once 'header.inc';
$html = '';
foreach ($data['schemas'] as $key => $value)
{
  $html .= getRow($value, "schemas");
}
$html .=
<<<HTML
                  <tr>
                    <td colspan="6"><h3 style="margin-top: 10px; padding: 0;">RDA Vocabularies</h3></td>
                  </tr>
HTML;
foreach ($data['vocabs'] as $key => $value)
{
  $html .= getRow($value, "vocabs");
}

echo $html;
require_once 'footer.inc';

exit();

/**
* description
*
* @return return_type
* @param  var_type $var
*/
function getData($apiUrl)
{
  global $useFopen;

  if ($useFopen)
  {
    $fp = fopen($apiUrl, 'rb', false);
    if ($fp)
    {
      //set the correct header
      fpassthru($fp);
      fclose($fp);
    }
    else
    {
      //debug_print_backtrace();
      header(' ', true, 404);
    }
  }
  else
  {
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    $data = curl_exec($ch);
    //for testing
    //echo '<span style="background-color:cyan">' . curl_getinfo($ch) . '</span>';

    curl_close($ch);
    return $data;
  }
}

/**
* description
*
* @return return_type
* @param  var_type $var
*/
function getLocalData($type)
{
  global $apiUrl, $domain, $useFopen, $updateInterval, $dataFolder, $forceReload;

  $filePath  = $dataFolder . DIRECTORY_SEPARATOR . $type . "data.dat";
  $checkPath = $dataFolder . DIRECTORY_SEPARATOR . $type . "check";

  //check the last update date of the local cache
  if (!file_exists($checkPath))
  {
    touch($checkPath, strtotime("10 September 2000"));
  }

  $checkTime  = ($forceReload) ? 0 : filemtime($checkPath) + $updateInterval;
  $dateLocal  = ($forceReload) ? 0 : filemtime($filePath);
  $dateRemote = $dateLocal;

  //only get the update if 5 minutes have elapsed (discourage churning)
  if ($checkTime < time())
  {
    $Url = $apiUrl . "$type/lastupdate?domain=" . urlencode($domain);
    $dateRemote = getData($Url);
    touch($checkPath, time());
  }

  //this should fail if we can't get the remote data, which is ok
  if ($dateRemote > $dateLocal)
  {
    //get update of all of the schema/vocab data for domain
    $Url = $apiUrl . "$type/getinfo?domain=" . urlencode($domain) . "&type=php";
    $remoteData = getData($Url);
    $data = unserialize($remoteData);
    //save to file
    $success = file_put_contents($filePath, $remoteData);
    if ($success)
    {
      //touch the file to mark the date
      touch($filePath, $dateRemote);
    }
  }
  else
  {
    //get the data from the local file
    $data = unserialize(file_get_contents($filePath));
  }

  return $data;
}

/**
* description
*
* @return return_type
* @param  var_type $var
*/
function getRow($value, $type)
{
  global $registryUrl;
  $local['schemas']['history']   = "schemahistory/feed/schema_id" ;
  $local['vocabs']['history']    = "history/feed/vocabulary_id" ;
  $local['schemas']['list']      = "schemaprop/list/schema_id" ;
  $local['vocabs']['list']       = "concept/list/vocabulary_id" ;
  $local['schemas']['listLabel'] = "Element list" ;
  $local['vocabs']['listLabel']  = "Concept list" ;
  $local['schemas']['show']      = "schema/show/id" ;
  $local['vocabs']['show']       = "vocabulary/show/id" ;
  $history = $local[$type]['history'];
  $list = $local[$type]['list'];
  $listLabel = $local[$type]['listLabel'];
  $show = $local[$type]['show'];
  $id = $value['id'];
  $count = $value['count'];
  $name = htmlspecialchars($value['name']);
  $note = htmlspecialchars($value['note']);
  $status = $value['status'];
  $title = $note ? 'title="' . $note . '"': '';
	$noteImage = $note ? '<img alt="note" align="top" title="' . $note . '" src="/images/note2.gif" style="width: 8px; height: 10px;" />' : '' ;
  $uri = $value['uri'];
  $html =
  <<<HTML
                  <tr>
                    <td><a href="$registryUrl/$show/$id.html">$name</a></td><td>$noteImage</td>
										<td class="nowrap"><a href="$registryUrl/$list/$id.html">$listLabel ($count)</a></td>
										<td class="nowrap"><img align="top" alt="RDF/XML" src="/images/rdf_flyer.24.gif" style="width: 16px; height: 15px;" />
                      <a href="$uri.rdf" rel="alternate" type="application/rdf+xml">RDF/XML</a>&nbsp;</td>
										<td class="nowrap"><img align="top" alt="Feed-icon" src="/images/feed-icon.gif" style="width: 16px; height: 16px;" />
                      <a href="$registryUrl/$history/$id.rss" rel="alternate" title="RSS 2.01 History Feed" type="application/rss+xml">rss2</a>&nbsp;
                      <a href="$registryUrl/$history/$id.rdf" rel="alternate" title="RSS 1.0 (RDF) History Feed" type="application/rdf+xml">rss1</a>
                      <a href="$registryUrl/$history/$id.atom" rel="alternate" title="Atom 1.0 History Feed" type="application/atom+xml">atom</a></td>
										<td class="nowrap">$status</td>
                  </tr>
HTML;

  return $html;
}

/**
* callback function to lowercase an array
*
* @param  string $value
* @param  string $key
*/
function lc(&$value, $key)
{
  $key = strtolower($key);
}

exit;