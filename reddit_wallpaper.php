<?php

function GetStringValueFromElement($Parent, $ElementName) {
        // Set the pubDate
        $Result = '';
        // Should only be one, if there are more, use the last one
        foreach($Parent->getElementsByTagName($ElementName) as $Element) {
                $Result = htmlspecialchars($Element->nodeValue);
        }
	return $Result;
}

header("Content-Type: application/xml; charset=ISO-8859-1");

// Default resolution
$resolution = "1920x1080";

// Parse headers
if (!empty($_GET))
{
	if (($_GET["r"] != "") && ($_GET["r"] != null) && (isset($_GET["r"]))) {
		// Use only valid input for resolution in format <width>x<height>
		$reso_array = explode("x",$_GET["r"]);
		if ((count($reso_array) == 2) && (is_numeric($reso_array[0])) && (is_numeric($reso_array[1]))) {
			$resolution = $reso_array[0]."x".$reso_array[1];
		}
	}
}

// Create input object & fetch reddit RSS feed
$InputXml = new DomDocument;
$FetchUrl = "https://www.reddit.com/r/EarthPorn+SkyPorn+SpacePorn+AbandonedPorn+CityPorn/search.rss?q=".$resolution."+nsfw%3Ano&restrict_sr=on&sort=top&t=year&limit=100"; 
$InputXml->load($FetchUrl);

// Create output object for generating XML output
$OutputXml = new DomDocument('1.0', 'UTF-8');

$RssRoot = $OutputXml->createElement('rss');
$RssVersion = $OutputXml->createAttribute('version');
$RssVersion->value = '2.0';
$RssRoot->appendChild($RssVersion); 

// Create the <channel>
$RssChannel = $OutputXml->createElement('channel');

// Set <title> and <link> for <channel>
$RssChannel->appendChild($OutputXml->createElement('title','Reddit images'));
$RssChannel->appendChild($OutputXml->createElement('link',htmlspecialchars($FetchUrl)));


// Copy each item from input to output
foreach($InputXml->getElementsByTagName('item') as $Item) {
	
	// Create output <item>, append <title>,<pubDate>,<link>,<category> & <description>
	$ItemOutput = $OutputXml->createElement('item');
	$ItemOutput->appendChild($OutputXml->createElement('title',GetStringValueFromElement($Item, 'title')));
	$ItemOutput->appendChild($OutputXml->createElement('pubDate',GetStringValueFromElement($Item, 'pubDate')));
	$ItemOutput->appendChild($OutputXml->createElement('link',GetStringValueFromElement($Item, 'link')));
	$ItemOutput->appendChild($OutputXml->createElement('category',GetStringValueFromElement($Item, 'category')));
	
        // Set the Description
        $Description = '';
        // Should only be one, if there are more, use the last one
        foreach($Item->getElementsByTagName('description') as $Element) {
                $Description = $Element->nodeValue;
        }


        // Add the <description> to <item>
        $ItemOutput->appendChild($OutputXml->createElement('description',htmlspecialchars($Description)));

	// Load DomDocument to parse the description
       	$ItemDescription = new DomDocument;
        @$ItemDescription->loadHTML($Description);

	// Looking for the link target of the text '[link]'
	$ImageUrl = '';
        foreach($ItemDescription->getElementsByTagName('a') as $link) {
                if ($link->nodeValue == '[link]') {
			$ImageUrl = $link->getAttribute('href');
                }
        }

	// Imgur fix for indirectly linked images
	if ((substr($ImageUrl, -4) != '.jpg') && (substr($ImageUrl, 0, 17) == 'http://imgur.com/')) {
		$ImageUrl = str_replace('http://imgur.com/','http://i.imgur.com/',$ImageUrl);
		$ImageUrl .= ".jpg";
	}
	
	// Put the image URL in an enclosure (required by windows wallpaper)
	$NewEnclosure = $OutputXml->createElement('enclosure');
	$NewEnclosureURL = $OutputXml->createAttribute('url');
	$NewEnclosureURL->value = $ImageUrl;
	$NewEnclosure->appendChild($NewEnclosureURL);

	$ItemOutput->appendChild($NewEnclosure);

	// Image extension must be .jpg and an extra filter for resolution, because reddit also returns <height>x<width> for a <width>x<height> search
       	if ((substr($ImageUrl, -4) == '.jpg') && (strpos(GetStringValueFromElement($Item, 'title'),$resolution) !== false)) {
		$RssChannel->appendChild($ItemOutput);
	}
}

$RssRoot->appendChild($RssChannel);
$OutputXml->appendChild($RssRoot);

// Ouput to XML
echo $OutputXml->saveXML();

?>
