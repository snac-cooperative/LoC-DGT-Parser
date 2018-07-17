<?php
/**
 * LoC DGT Parsing Tool
 *
 * Parses the XML version of the MARC, as transformed by Daniel. 
 *
 * @author Robbie Hott
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 * @copyright 2018 the Rector and Visitors of the University of Virginia
 */

if ($argc < 2) {
    die("Usage: php parselc.php marcxmlfile.xml\n");
}

$xml = simplexml_load_string(file_get_contents($argv[1]));
$json = json_encode($xml, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
$arr = json_decode($json, true);

$vocab = [];

foreach ($xml->children() as $record) {
    $local = [];
    $local["categories"] = [];
    $local["broader"] = [];
    $local["related"] = [];
    foreach ($record->children() as $name => $c) {
        if ($name == "controlfield") {
            // get id
            if (hasAttribute($c, "tag", "001"))
                $local["id"] = "$c";
        } else if ($name == "datafield") {
            // get type
            if (hasAttribute($c, "tag", "072")) {
                foreach ($c->children() as $sf => $v) {
                    if ($sf == "subfield" && hasAttribute($v, "code", "a"))
                        array_push($local["categories"], "$v");
                }
            }
            // get preferred
            if (hasAttribute($c, "tag", "150")) {
                foreach ($c->children() as $sf => $v) {
                    if ($sf == "subfield" && hasAttribute($v, "code", "a"))
                        $local["preferred"] =  "$v";
                }
            }
            // get alternate
            if (hasAttribute($c, "tag", "450")) {
                foreach ($c->children() as $sf => $v) {
                    if ($sf == "subfield" && hasAttribute($v, "code", "a")) {
                        if (!isset($local["alternate"]))
                            $local["alternate"] = [];
                        array_push($local["alternate"], "$v");
                    }
                }
            }
            // get related
            if (hasAttribute($c, "tag", "550")) {
                $term = null;
                $broader = false;
                foreach ($c->children() as $sf => $v) {
                    if ($sf == "subfield" && hasAttribute($v, "code", "w") && $v == 'g')
                        $broader = true;
                    if ($sf == "subfield" && hasAttribute($v, "code", "0"))
                        $term = $v;
                }
                if ($broader && $term != null) {
                    array_push($local["broader"], "$term");
                } else if (!$broader && $term != null) {
                    array_push($local["related"], "$term");
                }
            }

        }
    }
    if (isset($local["id"])) {
        $vocab[$local["id"]] = $local;
    }
}

// Print out a JSON object containing all the vocabulary
echo json_encode($vocab, JSON_PRETTY_PRINT);

// Print out notices if any of the vocabluary are contained in multiple categories
foreach ($vocab as $i => $t) {
    if (count($t["categories"]) > 1)
        echo "\n$i ({$t["preferred"]}) had multiple categories";
}
echo "\n";



// Helper function to make getting attribute values easier
function hasAttribute(&$xml, $att, $val=null) {
    foreach ($xml->attributes() as $k=>$v) {
        if ($k == $att) {
            if ($val != null)
                return $val == $v;
            return $v;
        }
    }
    return false;
}
