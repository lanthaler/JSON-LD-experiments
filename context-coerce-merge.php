<?php

/**
 * Prefix expansion
 *
 * @param string $value      the IRI that should be expanded
 * @param array  $loclctx    the local context
 * @param array  $activectx  the active context
 * @param array  $path       a path of already processed terms
 */
function prefixExpansion($value, $loclctx, $activectx, $path = array())
{
  if(strpos($value, ':') === false)
    return $value;  // not prefix:suffix

  list($prefix, $suffix) = explode(':', $value, 2);

  if(in_array($prefix, $path))
  {
    print '<p style="color: red">Cycle detected! Path: ' .
      join(' -> ', $path) . ' -> ' . $path[0] . '</p>';
    return null;

    // Alternative:
    // throw new Exception("Cycle detected: " . join(' -> ', $path) . ' -> ' . $path[0]);
  }
  else
  {
    $path[] = $prefix;
  }

  if(array_key_exists($prefix, $loclctx))
  {
    $expanded = prefixExpansion($loclctx[$prefix], $loclctx, $activectx, $path);
    return (is_null($expanded)) ? null : $expanded . $suffix;

    // ... with exceptions:
    // return prefixExpansion($loclctx[$prefix], $loclctx, $activectx, $path) . $suffix;
  }

  if(array_key_exists($prefix, $activectx))
  {
    // all values in the active context have already been expanded
    return $activectx[$prefix] . $suffix;
  }

  return $value;
}


/**
 * Update active context
 *
 * If cycles are detected, the definitions are simply ignored.
 *
 * @param array  $loclctx    the local context
 * @param array  $activectx  the active context
 */
function updateActiveContext($loclctx, &$activectx)
{
  foreach($loclctx as $key => $value)
  {
    if(array_key_exists($key, $activectx) && is_string($activectx[$key]))
    {
      $activectx[$key] = array('@iri' => $activectx[$key]);
    }

    if(is_string($value))
    {
      // either IRI or prefix:suffix
      $expanded = prefixExpansion($value, $loclctx, $activectx);

      if(!is_null($expanded))
      {
        $loclctx[$key] = $expanded;
        $activectx[$key]['@iri'] = $expanded;
      }
    }
    else if(is_array($value))
    {
      if(array_key_exists('@iri', $value))
      {
        $expanded = prefixExpansion($value['@iri'], $loclctx, $activectx);

        if(!is_null($expanded))
        {
          $loclctx[$key]['@iri'] = $expanded;
          $activectx[$key]['@iri'] = $expanded;
        }
      }

      if(array_key_exists('@datatype', $value))
      {
        $expanded = prefixExpansion($value['@datatype'], $loclctx, $activectx);

        if(!is_null($expanded))
        {
          $loclctx[$key]['@datatype'] = $expanded;
          $activectx[$key]['@datatype'] = $expanded;
        }
      }
    }
    else
    {
      print 'ERROR, invalid value: ' . var_dump($loclctx);
    }

    // replace array with string if there is just @iri
    if(array_key_exists($key, $activectx) && is_array($activectx[$key]) &&
       (count($activectx[$key]) === 1) && array_key_exists('@iri', $activectx[$key]))
    {
      $activectx[$key] = $activectx[$key]['@iri'];
    }
  }
}



//--------------------------------------------------------------------------


// In the active context everything is already expanded!
$activectx = '
{
  "@context": {
    "xsd": "http://www.w3.org/2001/XMLSchemaOLD#",
    "foaf": "http://xmlns.com/foaf/0.1/",
    "author": {
      "@iri": "http://xmlns.com/foaf/0.1/name",
      "@datatype": "http://www.w3.org/2001/XMLSchemaOLD#string"
    }
  }
}';

print '<h2>Active context</h2>';
print "<pre>$activectx</pre>\n";
print("<hr />");

$loclctx = '
{
  "@context": {
    "xsd": "http://www.w3.org/2001/XMLSchema#",
    "dc": "http://purl.org/dc/terms/",
    "date": {
      "@iri": "dc:date",
      "@datatype": "xsd:date"
    },
    "authortest" : "author:test",
    "author": "dc:author/",
    "homepage": "foaf:homepage",
    "a": "c:/",
    "b": "a:b",
    "c": "b:/c"
  }
}';

print '<h2>Local context</h2>';
print "<pre>$loclctx</pre>\n";
print("<hr />Processing local context..");


$activectx = json_decode($activectx, true);
$activectx = $activectx['@context'];

$loclctx = json_decode($loclctx, true);
$loclctx = $loclctx['@context'];


updateActiveContext($loclctx, $activectx);
print '<h2>Updated active context</h2>';
var_dump($activectx);
