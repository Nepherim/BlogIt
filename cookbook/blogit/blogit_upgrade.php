<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit_convert.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usage instructions refer to: http://pmwiki.com/wiki/Cookbook/BlogIt
*/
global $RecipeInfo,$SitemapSearchPatterns;
if (!isset($RecipeInfo['BlogIt']['Version']))  Abort("<h3>Run this script by using ?action=blogitupgrade on any page.</h3>");

$PageTextVarPatterns['(::var:...::)'] = '/(\(:: *(\w[-\w]*) *:(?!\))\s?)(.*?)(::\))/s'; #legacy format pre-2009-10-19

#default: default value, if no old_value present.
#format: final structure of field -- $1 replaced with repeated_format.
$bi_ConvertRules = array(
	'2009-10-19'=>array(
		'old'=>array(
			'pmmarkup'=> array(), 'blogid'=> array(), 'entrytype'=> array(), 'entrydate'=> array(), 'entryauthor'=> array(), 'entrytitle'=> array(),
			'entrystatus'=> array(), 'entrycomments'=> array(), 'entrytags'=> array(), 'entrybody'=> array()
		),
		'new'=>array(
			'pmmarkup'=> array('format'=>'[[#blogit_pmmarkup]]$1[[#blogit_pmmarkupend]]'),
			'blogid'=> array(), 'entrytype'=> array(), 'entrydate'=> array(), 'entryauthor'=> array(), 'entrytitle'=> array(),
			'entrystatus'=> array(), 'entrycomments'=> array(), 'entrytags'=> array(),
			'entrybody'=> array('format'=>'[[#blogit_entrybody]]$1[[#blogit_entrybodyend]]')
		)),
	'convert'=>array(
		'old'=>array(
			'pmmarkup'=> array(), 'blogid'=> array(), 'entrytype'=> array(), 'entrydate'=> array(),
			'entrytitle'=> array('format'=>'\(:title (.*):\)'),
			'entrystatus'=> array(), 'entrycomments'=> array(), 'entrytags'=> array('format'=>'\[\[\!(.*?)\]\]'), 'entrybody'=> array()
		),
		'new'=>array(
			'pmmarkup'=> array('default'=>'bi_GetPmMarkup($org["text"], "", $org["title"])',
				'format'=>'[[#blogit_pmmarkup]]$1[[#blogit_pmmarkupend]]'),
			'blogid'=> array('default'=> '(isset($_GET["blogid"]) ?$_GET["blogid"] :"blog1")'),
			'entrytype'=> array('default'=> 'blog'),
			'entrydate'=> array('default'=> '$org["ctime"]'),
			'entryauthor'=> array('default'=> '$org["author"]'),
			'entrytitle'=> array('default'=> '(@$org["title"] ?str_replace("$", "&#036;", $org["title"]) :$AsSpacedFunction($name))'),
			'entrystatus'=> array('default'=> 'draft'),
			'entrycomments'=> array('default'=> 'open'),
			'entrytags'=> array('default'=>'bi_SaveTags($org["text"], "",$pn)'),
			'entrybody'=> array('default'=>'$org["text"]', 'format'=>'[[#blogit_entrybody]]$1[[#blogit_entrybodyend]]')
		))
	);

function bi_HandleUpgrade($src, $auth='admin'){
global $_GET,$RecipeInfo,$bi_ConvertRules;
	$first = array_keys($bi_ConvertRules);
	$mode = (@$_GET['mode'] ?$_GET['mode'] :'upgrade');
	$version = (@$_GET['version'] ?$_GET['version']
		:(array_key_exists($RecipeInfo['BlogIt']['Version'],$bi_ConvertRules) ?$RecipeInfo['BlogIt']['Version'] :$first[0]));
	$pl = ListPages('/^('. (isset($_GET['pattern']) ?str_replace(',','|',$_GET['pattern']) :$src) .')/');
	if ($mode=='upgrade')  bi_Convert($src, $auth, $version, $pl, $mode);
	elseif ($mode=='convert'||$mode=='revert')  bi_Convert($src, $auth, $mode, $pl, $mode);
	exit;  #Prevent original page loading.
}

#pattern: Group.*,Group.Page, etc.
#writetofile: only writes to file if this is true -- otherwise outputs results to browser.
#mode=upgrade|convert|revert
#blogid=string
function bi_Convert($src, $auth='admin', $dataset, $pagelist, $mode) {
global $bi_ConvertRules,$bi_TagSeparator,$_GET,$SearchPatterns;
	$datarules = $bi_ConvertRules[$dataset];
	$pagelist=MatchPageNames($pagelist,$SearchPatterns['default']);

	foreach ($pagelist as $i => $pn) {
		list($group, $name) = explode('.', $pn);  #$name used to derive title.
		$pagetext = '';
		$org = RetrieveAuthPage($pn,$auth,0, READPAGE_CURRENT);
		echo("<b>$pn</b><br/>");
		if (!$org) {echo('No admin privs on page.<br/>'); continue;}

		$entryType = PageTextVar($pn,'entrytype');
		if ( ($mode=='convert' && empty($entryType)) || ($mode=='upgrade' && $entryType=='blog')) {

			#populate $new_field_val array for each $new_field_name based on $new_field_rules
			foreach ($datarules['new'] as $new_field_name=>$new_field_rules){
				$new_field_val[$new_field_name] = '';

				#is the new field based on an old_field or was the field defined in the prior version, with the same name?
				if (isset($datarules['old'][$new_field_name])){
					$new_field_val[$new_field_name] = PageTextVar($pn,$new_field_name);
					# Get basic separated list with no formatting
					if ($datarules['old'][$old_field]['format'])
						$new_field_val[$new_field_name] = implode($bi_TagSeparator,
							(preg_match_all('/'.$datarules['old'][$old_field]['format'].'/', $new_field_val[$new_field_name], $m) ? $m[1] : array())
						);
				}

				# Set default value if none calculated so far
				if (empty($new_field_val[$new_field_name]) && isset($datarules['new'][$new_field_name]['default']))
					$new_field_val[$new_field_name] = eval('return ('.$datarules['new'][$new_field_name]['default'].');');

				# Format the field
				if (isset($datarules['new'][$new_field_name]['format']))
					$new_field_val[$new_field_name] = str_replace('$1',$new_field_val[$new_field_name],$datarules['new'][$new_field_name]['format']);
				else
					$new_field_val[$new_field_name] = '(:'.$new_field_name.':'.$new_field_val[$new_field_name].':)';

				$pagetext .= $new_field_val[$new_field_name]."\n";
			}
		}elseif ($mode=='revert' && $entryType == 'blog'){
			$pagetext = PageTextVar($pn,'entrybody')."\n\n".PageTextVar($pn,'pmmarkup');
		}else  echo('Nothing to '.$mode .'<br/>');

		if ($_GET['writetofile']=='true') {
			if (!empty($pagetext)){
				$new = $org;
				$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'BlogIt Format: '.$mode;
				$new['diffclass']='minor';
				$new['text'] = $pagetext;
				PostPage($pn,$org,$new);  #Don't need UpdatePage, as we don't require edit functions to run
				echo('BlogIt page attributes written.<br/>');
			}else
				echo('Nothing to write.<br/>');
		}
		echo (str_replace("\n",'<br/>', $pagetext.'<br/>'));
	}
}
