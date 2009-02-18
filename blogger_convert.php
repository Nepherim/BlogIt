<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogger_convert.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usages instructions refer to: http://pmwiki.com/Cookbooks/Blogger
*/
if (!isset($RecipeInfo['Blogger'])) include_once("$FarmD/cookbook/blogger/blogger.php");
SDV($HandleActions['bloggerconvert'], 'blogger_ConvertPage'); SDV($HandleAuth['bloggerconvert'], 'admin');

function blogger_ConvertPage($src, $auth='admin') {
	$blogid = (isset($GLOBALS['_GET']['blogid']) ? $GLOBALS['_GET']['blogid'] : $GLOBALS['Blogger_BlogList']['blog1']);
	$old = RetrieveAuthPage($src,$auth,0, READPAGE_CURRENT);
	if($old){
		$new = $old;
		$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'Convert to Blogger format';
		$_POST['diffclass']='minor';
		$new['text'] = "(:blogid:" .$blogid .":)\n(:entrytype:" .$GLOBALS['Blogger_PageType']['blog'] .":)\n"
			."(:entrydate::)\n(::entrytitle:" .(empty($old['title'])?'':'(:title '.$old['title'].':)') ."::)\n"
			."(:entrystatus:" .$GLOBALS['Blogger_StatusType']['draft'] .":)\n"
			."(:entrycomments:" .$GLOBALS['Blogger_CommentType']['open'] .":)\n(::entrytags:::)\n"
			."(::entrybody:" .$old['text'] ."::)";
		PostPage($src,$old,$new);	#Don't need UpdatePage, as we don't require edit functions to run
	}
	Redirect($src);
}
