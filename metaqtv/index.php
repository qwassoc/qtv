<?php

	// disable caching, from PHP manual
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");


	require "config.php";
	if (DEBUG_MODE) {
		error_reporting(E_ALL);
	}
	else {
		error_reporting(0);
	}
	
	$qtvlist = NULL;
	require "qtvlist.php";
	
	$cururl = "";
	$intable = false;
	$tablelev = 0;
	$ignoreline = false;
	$errors = "";
	$ignore_empty = IGNORE_EMPTY && !isset($_GET["full"]);
	
	// outpu buffers
	$output_arr = array();
	$output_buf = "";
	$output_val = "";
	
	// when not false we are inside an element which contains number of observers info
	$inobservers = false;
	
	// type of server line
	$line_type = false;
	
	function output($s)
	{
		global $output_buf;
		
		$output_buf .= $s;
	}
	
	function output_setval($v)
	{
		global $output_val;
		
		$output_val .= $v;
	}
	
	function output_finish()
	{
		global $output_arr;
		global $output_buf;
		global $output_val;
		global $line_type;
		
		$ov = (int) trim($output_val);
		if ($line_type == "empty") $ov = -1;
		array_push($output_arr, array( 0 => $output_buf, 1 => $ov, 2 => count($output_arr) ));
		
		$output_buf = "";
		$output_val = "";
	}
	
	function cmp_rows($a, $b)
	{
		if ($a[1] == $b[1]) {
			return ($a[2] < $b[2]) ? -1 : 1;
		}
		else return ($a[1] > $b[1] ? -1 : 1);
	}
	
	function output_dump()
	{
		global $output_arr;
		global $ignore_empty;
		
		if ($ignore_empty) {
			usort($output_arr, "cmp_rows");
		}
		
		foreach ($output_arr as $r)
		{
			echo $r[0];
		}
		
		$output_arr = array();
	}
	
	function startElement($parser, $name, $attrs)
	{
		global $intable;
		global $tablelev;
		global $cururl;
		global $ignoreline;
		global $ignore_empty;
		global $inobservers;
		global $line_type;
		
		if ($tablelev == 1 && $name == "tr") {
			if (strpos(@$attrs["class"], "nebottom") !== false) $line_type = "second";
			else if (strpos(@$attrs["class"], "netop") !== false) $line_type = "first";
			else $line_type = "empty";
		}

		// we are entering a row which represents an empty server
		// so if user wants, we will set ignore flag on
		if ($name == "tr" && $ignore_empty && $tablelev == 1) {
			$ignoreline = $line_type == "empty";
		}
		
		if ($ignoreline) {
			return;
		}
		
		if (@$attrs["class"] == "observers" || $inobservers) {
			$inobservers = $name;
		}
		
		if ($intable) {
			output("<".$name);
			
			foreach($attrs as $k => $v) {
				if ($k == "href" || $k == "src") {
					if ($v[0] == "/") {
						$v = substr($v, 1);
					}
					$v = $cururl.$v;
				}
				output(' '.htmlspecialchars($k).'="'.htmlspecialchars($v).'"');
			}
			
			output(">");
		}
		
		if (!$intable && @$attrs["id"] == "nowplaying") {
			$intable = true;
		}
		
		if ($intable && $name == "table") {
			$tablelev++;
		}		
	}
	
	function endElement($parser, $name)
	{
		global $intable;
		global $tablelev;
		global $ignoreline;
		global $inobservers;
		global $line_type;
		
		if ($ignoreline) {
			return;
		}
		
		if ($intable && $name == "table") {
			$tablelev--;
			if (!$tablelev) {
				$intable = false;
			} 
		}
		
		if ($intable) {
			output ("</".$name.">");
		}
		
		if ($name == "tr" && $tablelev == 1 && ($line_type == "second" || ($line_type == "empty"))) {
			output_finish();
		}
		
		if ($inobservers == $name) {
			$inobservers = false;
		}
	}
	
	function cdata($parser, $d) {
		global $intable;
		global $ignoreline;
		global $inobservers;
		
		if ($intable && !$ignoreline) {
			output($d);
		}
		
		if ($inobservers == "span" && !$ignoreline) {
			output_setval($d);
		}
	}
	
	function defaultdata($parser, $data)
	{
		global $ignoreline;
	  
		if (!$ignoreline) {
			output($data);
		}
		return TRUE;
	}
	
	/**
  * Translate literal entities to their numeric equivalents and vice
versa.
  *
  * PHP's XML parser (in V 4.1.0) has problems with entities! The only
one's that are recognized
  * are &amp;, &lt; &gt; and &quot;. *ALL* others (like &nbsp; &copy;
a.s.o.) cause an 
  * XML_ERROR_UNDEFINED_ENTITY error. I reported this as bug at
http://bugs.php.net/bug.php?id=15092
  * The work around is to translate the entities found in the XML source
to their numeric equivalent
  * E.g. &nbsp; to &#160; / &copy; to &#169; a.s.o.
  * 
  * NOTE: Entities &amp;, &lt; &gt; and &quot; are left 'as is'
  * 
  * @author Sam Blum bs_php@users.sourceforge.net
  * @param string $xmlSource The XML string
  * @param bool   $reverse (default=FALSE) Translate numeric entities to
literal entities.
  * @return The XML string with translatet entities.
  */
	function _translateLiteral2NumericEntities($xmlSource, $reverse =
FALSE) {
    static $literal2NumericEntity;
    
    if (empty($literal2NumericEntity)) {
      $transTbl = get_html_translation_table(HTML_ENTITIES);
      foreach ($transTbl as $char => $entity) {
        if (strpos('&"<>', $char) !== FALSE) continue;
        $literal2NumericEntity[$entity] = '&#'.ord($char).';';
      }
    }
    if ($reverse) {
      return strtr($xmlSource, array_flip($literal2NumericEntity));
    } else {
      return strtr($xmlSource, $literal2NumericEntity);
    }
  }
	
	function InsertUrl($url)
	{
		global $xml_parser;
		global $intable;
		global $tablelev;
		global $cururl;
		global $errors;
		global $ignoreline;

		$intable = false;
		$tablelev = 0;
		$ignoreline = false;
		
		$cururl = $url;
		$fp = @fopen($url, "r");
		if (!$fp) {
			$errors .= "<p>Couldn't open {$url}</p>";
			return;
		}
		
		$xml_parser = xml_parser_create();
		if (!$xml_parser) {
			$errors .= "<p>Couldn't create XML parser</p>";
			return;
		}
		xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($xml_parser, "startElement", "endElement");
		xml_set_character_data_handler($xml_parser, "cdata");
		xml_set_default_handler($xml_parser, "defaultdata");
		
		$data = "";
		while (!feof($fp)) {
		    $chunk = fread($fp, 4096);
		    if ($chunk === false) {
		      $errors .= "<p>fread on stream ended with error</p>";
					break;
				}
				else {
					$data .= $chunk;
				}
		}
		$data = _translateLiteral2NumericEntities($data);
		if (!xml_parse($xml_parser, $data, true)) {
		  $err = xml_get_error_code($xml_parser);
			$errors .= "<p>XML Parser returned error ".$err."</p>";
		  $errors .= "<p>".xml_error_string($err)."</p>";
		}
		
		
		xml_parser_free($xml_parser);
		fclose($fp);
	}

	include "header.html";
	
	if ($ignore_empty) {
		foreach ($qtvlist as $url => $name) {
			InsertURL($url);
		}
		output_dump();
	}
	else {
		foreach ($qtvlist as $url => $name) {
			echo "<tr class='qtvsep'><td colspan='3'><a href='".htmlspecialchars($url)."'>".htmlspecialchars($name)."</a></td></tr>";
			InsertURL($url);
			output_dump();
		}
	}

	if (DEBUG_MODE && strlen($errors)) {
		echo "</table>";
		echo "<div id='errors'>".$errors."</div><table>\n";
	}
	include "foot.html";

?>
