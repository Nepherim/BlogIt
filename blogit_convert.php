<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit_convert.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usages instructions refer to: http://pmwiki.com/Cookbooks/BlogIt
*/
if (!isset($RecipeInfo['BlogIt'])) include_once("$FarmD/cookbook/blogit/blogit.php");
SDV($HandleActions['blogitconvert'], 'blogit_ConvertPage'); SDV($HandleAuth['blogitconvert'], 'admin');
SDV($HandleActions['blogitupgrade'], 'blogit_Upgrade'); SDV($HandleAuth['blogitupgrade'], 'admin');

SDVA($SitemapSearchPatterns, array());
$SitemapSearchPatterns[] = '!\.(All)?Recent(Changes|Uploads|Pages)$!';

function blogit_ConvertPage($src, $auth='admin') {
	$blogid = (isset($GLOBALS['_GET']['blogid']) ? $GLOBALS['_GET']['blogid'] : $GLOBALS['bi_BlogList']['blog1']);
	$old = RetrieveAuthPage($src,$auth,0, READPAGE_CURRENT);
	if($old){
		$new = $old;
		$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'Convert to BlogIt format';
		$_POST['diffclass']='minor';
		$new['text'] =
			"(::pmmarkup:" .(empty($old['title'])?'':'(:title '.$old['title'].':)') ."::)\n"
			."(:blogid:" .$blogid .":)\n"
			."(:entrytype:" .$GLOBALS['bi_PageType']['blog'] .":)\n"
			."(:entrydate::)\n"
			."(:entryauthor::)\n"
			."(:entrytitle:" .$old['title'] .":)\n"
			."(:entrystatus:" .$GLOBALS['bi_StatusType']['draft'] .":)\n"
			."(:entrycomments:" .$GLOBALS['bi_CommentType']['open'] .":)\n"
			."(:entrytags::)\n"
			."(::entrybody:" .$old['text'] ."::)";
		PostPage($src,$old,$new);	#Don't need UpdatePage, as we don't require edit functions to run
	}
	Redirect($src);
}

function blogit_Upgrade($src, $auth='admin') {
global $bi_BlogGroups, $SearchPatterns, $bi_PageType;
blogit_DebugLog('------- convert:START');
	$CurrentFields = array(
		'pmmarkup'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'blogid'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrytype'=> array('default'=> $GLOBALS['bi_PageType']['blog'], 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrydate'=> array('default'=> "$old['title']", 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entryauthor'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrytitle'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrystatus'=> array('default'=> $GLOBALS['bi_StatusType']['draft'], 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrycomments'=> array('default'=> $GLOBALS['bi_CommentType']['open'], 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrytags'=> array('default'=> '', 'style'=>'', 'format'=>'', 'old_fields'=>''),
		'entrybody'=> array('default'=> '', 'style'=>'(::entrybody:$1::)', 'format'=>'', 'old_fields'=>'')
	);

/* Aplha2
(:blogid:blog1:)
(:entrytype:blog:)
(:entrydate:1547228040:)
(::entrytitle:(:title Welcome to Blogger!:)::)
(:entrystatus:sticky:)
(:entrycomments:open:)
(::entrytags:[[!blogger]]::)
(::entrybody:
*/
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
	if (isset($_GET['pattern'])) $SearchPatterns['blogit'][] = '/^('. str_replace(',','|',$_GET['pattern']) .')/';
	elseif (isset($bi_BlogGroups)) $SearchPatterns['blogit'][] = '/^('. str_replace(',','|',$bi_BlogGroups) .')\./';

	$t = @ListPages($SearchPatterns['blogit']);
	foreach ($t as $i => $pn) {
		blogit_DebugLog('page:'.$pn);
		$old = RetrieveAuthPage($src,$auth,0, READPAGE_CURRENT);

		$entryType = PageTextVar($pn,'entrytype');
		if ($entryType == $bi_PageType['blog']) {
			blogit_DebugLog('convert:'.$pn);
			foreach ($OldFields as $k=>$v){
				$new[$k] = PageTextVar($pn,$k);
			}

		}
	}
	exit;
}
