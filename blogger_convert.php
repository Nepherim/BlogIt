<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogger_convert.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usages instructions refer to: http://pmwiki.com/Cookbooks/Blogger
*/
if (!isset($RecipeInfo['Blogger'])) include_once("$FarmD/cookbook/blogger/blogger.php");
SDV($HandleActions['bloggerconvert'], 'blogger_ConvertPage'); SDV($HandleAuth['bloggerconvert'], 'admin');
SDV($HandleActions['bloggerupgrade'], 'blogger_Upgrade'); SDV($HandleAuth['bloggerupgrade'], 'admin');

SDVA($SitemapSearchPatterns, array());
$SitemapSearchPatterns[] = '!\.(All)?Recent(Changes|Uploads|Pages)$!';

function blogger_ConvertPage($src, $auth='admin') {
	$blogid = (isset($GLOBALS['_GET']['blogid']) ? $GLOBALS['_GET']['blogid'] : $GLOBALS['Blogger_BlogList']['blog1']);
	$old = RetrieveAuthPage($src,$auth,0, READPAGE_CURRENT);
	if($old){
		$new = $old;
		$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'Convert to Blogger format';
		$_POST['diffclass']='minor';
		$new['text'] =
			"(::pmmarkup:" .(empty($old['title'])?'':'(:title '.$old['title'].':)') ."::)\n"
			."(:blogid:" .$blogid .":)\n"
			."(:entrytype:" .$GLOBALS['Blogger_PageType']['blog'] .":)\n"
			."(:entrydate::)\n"
			."(:entryauthor::)\n"
			."(:entrytitle:" .$old['title'] .":)\n"
			."(:entrystatus:" .$GLOBALS['Blogger_StatusType']['draft'] .":)\n"
			."(:entrycomments:" .$GLOBALS['Blogger_CommentType']['open'] .":)\n"
			."(:entrytags::)\n"
			."(::entrybody:" .$old['text'] ."::)";
		PostPage($src,$old,$new);	#Don't need UpdatePage, as we don't require edit functions to run
	}
	Redirect($src);
}

function blogger_Upgrade($src, $auth='admin') {
global $Blogger_BlogGroups, $SearchPatterns, $Blogger_PageType;
blogger_DebugLog('------- convert:START');

	$CurrentFields = array(
		'pmmarkup'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'blogid'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrytype'=> array('default'=> $GLOBALS['Blogger_PageType']['blog'], 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrydate'=> array('default'=> "$old['title']", 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entryauthor'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrytitle'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrystatus'=> array('default'=> $GLOBALS['Blogger_StatusType']['draft'], 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrycomments'=> array('default'=> $GLOBALS['Blogger_CommentType']['open'], 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrytags'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrybody'=> array('default'=> '', 'style'=>'(::entrybody:$1::)', 'format'=>'', 'old_fields'=>'')
	);
	$OldFields = array(
		'blogid'=> array('format'=>''),
		'entrytype'=> array('format'=>''),
		'entrydate'=> array('format'=>''),
		'entrytitle'=> array('format'=>'(:title $1:)'),
		'entrystatus'=> array('format'=>''),
		'entrycomments'=> array('format'=>''),
		'entrytags'=> array('format'=>'[[!$1]]'),
		'entrybody'=> array('format'=>'')
	);

	# pattern=Group.,Group.Page,Group.
	if (isset($_GET['pattern'])) $SearchPatterns['blogger'][] = '/^('. str_replace(',','|',$_GET['pattern']) .')/';
	elseif (isset($Blogger_BlogGroups)) $SearchPatterns['blogger'][] = '/^('. str_replace(',','|',$Blogger_BlogGroups) .')\./';

	$t = @ListPages($SearchPatterns['blogger']);
	foreach ($t as $i => $pn) {
		blogger_DebugLog('page:'.$pn);
		$old = RetrieveAuthPage($src,$auth,0, READPAGE_CURRENT);

		$entryType = PageTextVar($pn,'entrytype');
		if ($entryType == $Blogger_PageType['blog']) {
			blogger_DebugLog('convert:'.$pn);
			foreach ($OldFields as $k=>$v){
				$new[$k] = PageTextVar($pn,$k);
			}

		}
	}
	exit;
}
