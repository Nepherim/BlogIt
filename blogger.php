<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogger.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usages instructions refer to: http://pmwiki.com/Cookbooks/Blogger
*/
$RecipeInfo['Blogger']['Version'] = '2009-01-10';
if( $VersionNum<2001950 ) echo "<h3>You are running PmWiki version {$Version}. Blogger needs a newer version of PmWiki. Please update to the latest 2.2.0 beta version</h3>";
$blogger['debug']=true;
debugLog('--------------------');
#foreach ($_POST as $p=>$k) debugLog($p .'=' .$k, true);
#FPLCountA

# ----------------------------------------
# - Common user settable
# ----------------------------------------
SDV($Blogger_DefaultGroup, 'Blog');	#Pre-populates the Pagename field; blogs can exist in *any* group, not simply the default defined here.
SDV($Blogger_CommentGroup, 'Comments');
SDV($Blogger_CommentsEnabled, 'true');
SDV($Blogger_BlogGroups, 'Blog');	#OPTIONAL: Comma separated list of Blog groups. This is purely to speed up pagelists. Defining this list does not mean all pages in the group are 'blog-pages'.
SDV($Blogger_CategoryGroup, 'Tags');
SDV($Blogger_AuthorGroup, $AuthorGroup); #Defaults to 'Profiles'
SDV($Blogger_ReadMore, '%readmore%[[{$FullName}#break | Read more...]]');
SDV($Blogger_DateEntryFormat, '%d-%m-%Y %H:%M');
SDV($Blogger_DateDisplayFormat, $TimeFmt);
SDV($Blogger_BodyBreak, '[[#break]]');
SDV($Blogger_CoreTemplate, $SiteGroup .'/Blogger-CoreTemplate');
SDV($Blogger_SkinTemplate, (PageExists($SiteGroup .'/Blogger-SkinTemplate-'.$Skin)
	? $SiteGroup .'/Blogger-SkinTemplate-'.$Skin : $SiteGroup .'/Blogger-SkinTemplate'));
SDV($Blogger_NewEntry, $SiteGroup .'/Blogger-NewEntry');
SDV($Blogger_EnablePostDirectives, true); #Set to true to allow posting of directives of form (: :) in blog entries.
SDV($Blogger_TagSeparator, ', ');
SDVA($Blogger_StatusType, array('draft'=>'$[draft]', 'publish'=>'$[publish]'));
SDVA($Blogger_CommentType, array('open'=>'$[open]', 'readonly'=>'$[read only]', 'none'=>'$[none]'));
SDVA($Blogger_BlogList, array('blog1'=>'blog1'));  #Ensure 'blog1' key remains; you can rename the blog (2nd parameter). Also define other blogs.
SDVA($Blogger_PageType, array('blog'=>'blog'));  # INTERNAL USE ONLY

# ----------------------------------------
# - Usable on Wiki Pages
# ----------------------------------------
setFmtPV(array('Now','Blogger_AuthorGroup','Blogger_DefaultGroup','Blogger_CommentGroup','Blogger_CommentsEnabled','Blogger_CategoryGroup',
	'Blogger_DateEntryFormat','Blogger_DateDisplayFormat','Blogger_CoreTemplate','Blogger_SkinTemplate','Blogger_NewEntry','Blogger_BlogForm','Blogger_CommentForm'));
setFmtPVA(array('$Blogger_StatusType'=>$Blogger_StatusType, '$Blogger_CommentType'=>$Blogger_CommentType,
	'$Blogger_BlogList'=>$Blogger_BlogList, '$Blogger_PageType'=>$Blogger_PageType));

# ----------------------------------------
# - Internal
# ----------------------------------------
$Blogger_BlogForm = 'blogger-entry';
$Blogger_CommentForm = 'blogger-comments';
$Group = PageVar($pagename, '$Group');
$oldBrowse=$HandleActions['browse'];  #Store old browse action so we can perform actions prior.

# ----------------------------------------
# - PmWiki Config
# ----------------------------------------
# Prevent viewing source and diff, primarily for Comments, as this would reveal email.
$HandleAuth['source'] = $HandleAuth['diff'] = 'edit';
if ($Blogger_SkinTemplate == $SiteGroup .'/Blogger-SkinTemplate')
	$HTMLStylesFmt['blogger'] = 'h2 .blogger-edit-link a {font-size: 50%;}';
SDV($PageListCacheDir, $FarmD.'/work.d/');
SDV($EnablePageIndex, 1);
include_once($FarmD.'/cookbook/pmform.php');
include_once($FarmD.'/scripts/guiedit.php');
addPageStore();

# Doesn't pick up categories defined as page variables.
$LinkCategoryFmt = "<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>";
$CategoryGroup = $Blogger_CategoryGroup;	# Need to explicity set this.
$AutoCreate['/^' .$Blogger_CategoryGroup .'\./'] = array('ctime' => $Now);
if ($Group == $Blogger_CategoryGroup)
	$GroupFooterFmt = '(:include ' .$Blogger_CoreTemplate .'#tag-pagelist:)(:nl:)';

# Slow: Set to 1 to exclude listing any pages for which the browser does not currently have read authorization
$EnablePageListProtect = 0;
$SearchPatterns['default'][] = '!\\.(All)?Recent(Changes|Uploads|Comments)$!';
$SearchPatterns['default'][] = '!\\.Group(Print)?(Header|Footer|Attributes)$!';
$SearchPatterns['default'][] = '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki|' .$Blogger_CategoryGroup .')\\.!';
$SearchPatterns['default'][] = FmtPageName('!^$FullName$!', $pagename);

# Need to save entrybody in an alternate format (::entrybody:...::), to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['(::var:...::)'] = '/(\(:: *(\w[-\w]*) *:(?!\))\s?)(.*?)(::\))/s';
$PmForm[$Blogger_BlogForm] = 'form=' .$Blogger_CoreTemplate .'#blog-form fmt=' .$Blogger_CoreTemplate .'#blog-post';
$PmForm[$Blogger_CommentForm] = 'saveto="' .$Blogger_CommentGroup .'/{$Group}-{$Name}-' .date('Ymd\THms') .'" form=' .$Blogger_CoreTemplate .'#comment-form fmt=' .$Blogger_CoreTemplate .'#comment-post';

# ----------------------------------------
# - Main Processing
# ----------------------------------------
$entryType = PageVar($pagename,'$:entrytype');
debugLog('entryType: '.$entryType. '   action: '.$action. '    Target: '.$_POST['target']);
# Blog entry being posted from PmForm (new or existing)
if ($action && $action=='pmform'){  #Performed before PmForm action handler.
	if ($_POST['target']==$Blogger_BlogForm){
		# Change field delimiters from (:...:...:) to (::...:...::) for tags and body; entrybody MUST be the last variable.
		$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/s'] = '(::entrybody:$1::)';
		$ROSPatterns['/\(:entrytags:(.*?(:\))?):\)/'] = '(::entrytags:$1::)';
		saveTags();
		$_POST['ptv_entrydate'] = strtotime($_POST['ptv_entrydate']); #Convert from user entered format to Unix format
		if ( $Blogger_DefaultGroup && (empty($_POST['ptv_entryurl']) || $_POST['ptv_entryurl']==$Blogger_DefaultGroup.'.') )
			$_POST['ptv_entryurl'] = $Blogger_DefaultGroup .'.' .$_POST['ptv_entrytitle'];
		if ($Blogger_EnablePostDirectives == true)
			$PmFormPostPatterns = array();  #Null out the PostPatterns means that directive markup doesn't get replaced.

	}elseif ($_POST['target']==$Blogger_CommentForm && $Blogger_CommentsEnabled=='true'){
		$DefaultPasswords['edit']='';  #Remove edit password to allow initial posting of comment.
		$_POST['ptv_commentapproved'] = 'false';
	}
}else
	addMarkup();

if ($entryType && $entryType == trim($FmtPV['$Blogger_PageType_BLOG'],'\'')){
	$GroupHeaderFmt = '(:include ' .$Blogger_SkinTemplate .'#single-entry-view:)';  #Required for action=browse AND comments when redirected on error.
	if ($action=='bloggeredit' || ($action=='pmform' && $_POST['target']==$Blogger_BlogForm)){
		$GroupHeaderFmt = '(:include ' .$Blogger_CoreTemplate .'#blog-edit:)';  #Include GroupHeader on blog entry errors, as &action= is overriden by PmForms action.
	}
}

# ----------------------------------------
# - Markup Definitions
# ----------------------------------------
# (:blogger [intro,more] options:)text(:bloggerend:)
Markup('blogger', 'fulltext', '/\(:blogger ([more,intro,select,multiline]+)\s?(.*?):\)(.*?)\(:bloggerend:\)/esi',
	"bloggerMU_$1(PSS('$2'), PSS('$3'))");
function bloggerMU_more($options, $text){
	return (strpos($text, $GLOBALS['Blogger_BodyBreak']) !== false ? preg_replace('/{\$FullName}/', $options, $GLOBALS['Blogger_ReadMore']) : '');
}
function bloggerMU_intro($options, $text){
	list($found,$null) = explode($GLOBALS['Blogger_BodyBreak'], $text);
	return $found;
}
function bloggerMU_select($options, $text){
	list($var, $label) = split('/', $text);
	$i = count($GLOBALS[$var]);
	foreach ($GLOBALS[$var] as $k => $v)
		$t .= '(:input '. ($i==1?'hidden':'select') .' ' .$options .' "' .$k .'" "' .$v .'" id="' .$var .'":)';
	return ($i==1?'':$label).$t;
}
function bloggerMU_multiline($options, $text){
	return preg_replace('/\n/', '<br />', $text);  #Because pmform strips \n, and we end up with comments on a single line.
}

# ----------------------------------------
# - HandleActions Definitions
# ----------------------------------------
# If PmForms fails validation, and redirects to a browse, we need to define markup, since it isn't done as part of PmForm handling in Main Processing,
# as markup (tags) isn't processed if markup is defined.
$HandleActions['browse']='blogger_HandleBrowse';
function blogger_HandleBrowse($pagename){
	addMarkup();
	$GLOBALS['HandleActions']['browse']=$GLOBALS['oldBrowse'];
	HandleDispatch($pagename, 'browse');
}
SDV($HandleActions['bloggerapprove'], 'bloggerApproveComment');
SDV($HandleAuth['bloggerapprove'], 'admin');
function bloggerApproveComment($src, $auth='admin') {
	$ap = (isset($GLOBALS['_GET']['pn']) ? $GLOBALS['_GET']['pn'] : '');  #Page to approve
	if ($ap) $old = RetrieveAuthPage($ap,$auth,0, READPAGE_CURRENT);
	if($old){
		$new = $old;
		$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'Approved comment';
		$_POST['diffclass']='minor';
		$new['text'] = preg_replace('/\(:commentapproved:(false):\)/', '(:commentapproved:true:)',$new['text']);
		PostPage($ap,$old,$new);	#Don't need UpdatePage, as we don't require edit functions to run
	}
	Redirect($src);
}

# ----------------------------------------
# - Condition Definitions
# ----------------------------------------
$Conditions['blogger_ispage'] = 'bloggerIsPage($condparm)';
function bloggerIsPage($pn){
	$mp = MakePageName($GLOBALS['pagename'], $pn);
	if (empty($mp)) return true;
	if ($mp==$GLOBALS['pagename']) return false;
	return PageExists($mp);
}
$Conditions['blogger_isdate'] = 'bloggerIsDate($condparm)';
function bloggerIsDate($d){
	return false;
}
# ----------------------------------------
# - Markup Expression Definitions
# ----------------------------------------
# Parameters: 0:if/noif 1:variable 2:value 3:[&&,||]
$MarkupExpr['bloggerIfVar'] = '(!preg_match("/\\{.*?\\}/",$args[2])?(!empty($args[3])?$args[3]." ":"").($args[0]=="if"?"if=\\"":"")."equal {=\\$:$args[1]} $args[2]".($args[0]=="if"?"\\"":"") : "")';
$MarkupExpr['bloggerStripTags'] = 'implode($GLOBALS["Blogger_TagSeparator"],stripTags($args[0]))';
$MarkupExpr['ifnull'] = '(!empty($args[1])?$args[1]:$args[2])';
$MarkupExpr['bloggerBlogGroups'] = (empty($GLOBALS['Blogger_BlogGroups']) ? '""' : '"group=\"' .$GLOBALS['Blogger_BlogGroups'] .'\""');
$MarkupExpr['bloggerBasePage'] = 'bloggerBasePage($args[0])';
function bloggerBasePage($pn){
	return preg_replace('/^' .$GLOBALS['Blogger_CommentGroup'] .'[\/\.](.*?)-(.*?)-\d{8}T\d{6}$/','${1}/${2}',$pn);
}

# ----------------------------------------
# - Internal Functions
# ----------------------------------------
function addMarkup(){
	Markup('textvar::', '<split', '/\(::\w[-\w]*:(?!\)).*?::\)/s', '');  # Prevent (::...:...:) markup from being displayed.
}
# Combines categories in body [[!...]] with separated tag list in tag-field.
# Stores combined separated list in tag-field in PmWiki format [[!...]].
function saveTags() {
	global $_POST,$Blogger_TagSeparator;
	# Read tags from body, strip [[!...]]
	$bodyTags = stripTags($_POST['ptv_entrybody']);
	# Make sure tag-field entries are in standard separated format, and place in array
	if ($_POST['ptv_entrytags'])
		$fieldTags = explode($Blogger_TagSeparator, preg_replace('/'.trim($Blogger_TagSeparator).'\s*/', $Blogger_TagSeparator, $_POST['ptv_entrytags']));
	# Concatenate the tag-field tags, with those in the body,
	$allTags = array_unique(array_merge((array)$fieldTags, (array)$bodyTags));
	sort($allTags);
	#  generate a new separated string.
	$_POST['ptv_entrytags'] = ($allTags?'[[!'.implode(']]'.$Blogger_TagSeparator.'[[!', $allTags).']]':'');
}
# Returns an array of tags contained in [[!...]] markup within $src string.
function stripTags($src){
	return (preg_match_all('/\[\[\!(.*?)\]\]/', $src, $match) ? $match[1] : array());
}

# ----------------------------------------
# - General Helper Functions
# ----------------------------------------
function setFmtPV($a){
	foreach ($a as $k)
		$GLOBALS['FmtPV']['$'.$k]='$GLOBALS["'.$k.'"]';
}
# Sets $FmtPV variables named $key_VALUE. $a is an array with the key as the variable name, and values as indecies.
function setFmtPVA ($a){
	foreach ($a as $var=>$vals)
		foreach ($vals as $k=>$v)
			$GLOBALS['FmtPV'][$var .'_' .strtoupper($k)] = "'" .$v ."'";
}
function addPageStore ($n='wikilib.d'){
	$GLOBALS['PageStorePath'] = dirname(__FILE__) ."/" .$n ."/{\$FullName}";
	$where = count($GLOBALS['WikiLibDirs']);
	if ($where>1) $where--;
	array_splice($GLOBALS['WikiLibDirs'], $where, 0, array(new PageStore($GLOBALS['PageStorePath'])));
}
function debugLog ($msg, $out=false){
	if ($out || (!$out && $GLOBALS['blogger']['debug']) )
		error_log(date('r'). ' [blogger]: '. $msg);
}
