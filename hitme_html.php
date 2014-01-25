<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <title>HITME</title>
</head>
<body>
<h1>hitme</h1>

<pre>
<?php

  $headers = getHeaders2();
  echo nl2br(print_r($headers));


  function getHeaders2 ()
  {
    $headers = array();
    $keys    = preg_grep('{^HTTP_}i', array_keys($_SERVER));
    foreach ($keys as $val) {
      $key           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($val, 5)))));
      $headers[$key] = $_SERVER[$val];
    }
    return $headers;
  }
?>
</pre>
</body>
</html>

