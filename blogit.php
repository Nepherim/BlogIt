<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usages instructions refer to: http://pmwiki.com/Cookbooks/BlogIt
*/
$RecipeInfo['BlogIt']['Version'] = '2009-03-01';
if ($VersionNum < 2001950)
	Abort ("<h3>You are running PmWiki version {$Version}. BlogIt needs a newer version of PmWiki. Please update to 2.2.0 or later.</h3>");
$BlogIt['debug']=true;
bi_debugLog('--------------------');
#foreach ($_POST as $p=>$k) bi_debugLog($p .'=' .$k, true);
#FPLCountA

# ----------------------------------------
# - Common user settable
# ----------------------------------------
SDV($EnablePostCaptchaRequired, 1);
SDV($bi_DefaultGroup, 'Blog');	#Pre-populates the Pagename field; blogs can exist in *any* group, not simply the default defined here.
SDV($bi_CommentGroup, 'Comments');
SDV($bi_CommentsEnabled, 'true');
SDV($bi_BlogGroups, $bi_DefaultGroup);	#OPTIONAL: Comma separated list of Blog groups. This is purely to speed up pagelists. Defining this list does not mean all pages in the group are 'blog-pages'.
SDV($bi_CategoryGroup, 'Tags');
SDV($bi_AuthorGroup, 'Profiles'); #$AuthorGroup
SDV($bi_BodyBreak, '[[#break]]');
SDV($bi_TagSeparator, ', ');
SDV($bi_TitleSeparator, '-');
SDV($bi_EnablePostDirectives, true); #Set to true to allow posting of directives of form (: :) in blog entries.
SDV($bi_EntriesPerPage, 15);
SDV($bi_LinkToCommentSite, 'true');
SDV($bi_ReadMore, '%readmore%[[{$FullName}#break | Read more...]]');
SDV($bi_DateEntryFormat, '%d-%m-%Y %H:%M');
SDV($bi_DateDisplayFormat, $TimeFmt);
SDV($bi_DateISOFormat, '%Y%m%d');
SDV($bi_StatAction, $TotalCounterAction);  #set by TotalCounter cookbook
SDV($bi_NowISOFormat, strftime($bi_DateISOFormat, $Now));
SDVA($bi_StatusType, array('draft'=>'draft', 'publish'=>'publish', 'sticky'=>'sticky'));
SDVA($bi_CommentType, array('open'=>'open', 'readonly'=>'read only', 'none'=>'none'));
SDVA($bi_BlogList, array('blog1'=>'blog1'));  #Ensure 'blog1' key remains; you can rename the blog (2nd parameter). Also define other blogs.

#INTERNAL USE ONLY
SDV($bi_CoreTemplate, $SiteGroup .'.BlogIt-CoreTemplate');
SDV($bi_Admin, $SiteGroup .'.BlogIt-Admin');
SDV($bi_TemplateList, (isset($Skin)?$SiteGroup.'.BlogIt-SkinTemplate-'.$Skin.' ' : '') .$SiteGroup .'.BlogIt-CoreTemplate');
SDV($bi_NewEntry, $SiteGroup .'/BlogIt-NewEntry');
SDVA($bi_PageType, array('blog'=>'blog'));
SDV($bi_PageType_Comment, 'comment');  #Not in PageType list, since we don't want bloggers to be able to select 'comment' types.
if (CondAuth($pagename,'edit') || CondAuth($pagename,'admin'))	$EnablePostCaptchaRequired = 0;

SDV($FPLTemplatePageFmt, array(
	(isset($Skin)?$SiteGroup.'.BlogIt-SkinTemplate-'.$Skin : ''), $SiteGroup .'.BlogIt-CoreTemplate',
	'{$FullName}', '{$SiteGroup}.LocalTemplates', '{$SiteGroup}.PageListTemplates'
));

# ----------------------------------------
# - Usable on Wiki Pages
# ----------------------------------------
bi_setFmtPV(array('Now','bi_NowISOFormat', 'bi_DefaultGroup','bi_BlogGroups','bi_CommentGroup','bi_AuthorGroup',
	'bi_CommentsEnabled','bi_CategoryGroup','bi_DateEntryFormat','bi_DateDisplayFormat','bi_CoreTemplate','bi_NewEntry',
	'bi_BlogForm','bi_CommentForm', 'EnablePostCaptchaRequired', 'bi_EntriesPerPage','bi_Admin','bi_LinkToCommentSite',
	'bi_StatAction'
));
bi_setFmtPVA(array('$bi_StatusType'=>$bi_StatusType, '$bi_CommentType'=>$bi_CommentType,
	'$bi_BlogList'=>$bi_BlogList, '$bi_PageType'=>$bi_PageType
));

# ----------------------------------------
# - Internal
# ----------------------------------------
$bi_BlogForm = 'blogit-entry';
$bi_CommentForm = 'blogit-comments';
$Group = PageVar($pagename, '$Group');
$oldBrowse=$HandleActions['browse'];  #Store old browse action so we can perform actions prior.
$FmtPV['$bi_PageNext'] = (isset($_GET['page']) ? $_GET['page']+1 : 2);
$FmtPV['$bi_PagePrev'] = (isset($_GET['page']) && ($_GET['page']>0) ? $_GET['page']-1 : 0);
$FmtPV['$bi_EntryStart'] = (($FmtPV['$bi_PageNext']-2) * (isset($_GET['count']) ?$_GET['count'] :$bi_EntriesPerPage)) + 1;
$FmtPV['$bi_EntryEnd']   = $FmtPV['$bi_EntryStart'] + (isset($_GET['count']) ?$_GET['count'] :$bi_EntriesPerPage) - 1;

# ----------------------------------------
# - PmWiki Config
# ----------------------------------------
# Prevent viewing source and diff, primarily for Comments, as this would reveal email.
$HandleAuth['source'] = $HandleAuth['diff'] = 'edit';
include_once($FarmD.'/cookbook/pmform.php');
bi_addPageStore();
if ($bi_SkinTemplate == $SiteGroup .'/BlogIt-SkinTemplate-pmwiki')
	$HTMLStylesFmt['blogit'] = 'h2 .blogit-edit-link a {font-size: 50%;}';

# ----------------------------------------
# - Categories
# Doesn't pick up categories defined as page variables.
$LinkCategoryFmt = "<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>";
$CategoryGroup = $bi_CategoryGroup;	# Need to explicity set this.
$AutoCreate['/^' .$bi_CategoryGroup .'\./'] = array('ctime' => $Now);
if ($Group == $bi_CategoryGroup)
	$GroupFooterFmt = '(:includesection "#tag-pagelist":)(:nl:)';

# ----------------------------------------
# - SearchPatterns
$SearchPatterns['blogit'][] = '!\.(All)?Recent(Changes|Uploads|Comments)$!';
$SearchPatterns['blogit'][] = '!\.Group(Print)?(Header|Footer|Attributes)$!';
$SearchPatterns['blogit'][] = '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki)\.!';
$SearchPatterns['blogit'][] = FmtPageName('!^$FullName$!', $pagename);

# ----------------------------------------
# - PmForms
# Need to save entrybody in an alternate format (::entrybody:...::), to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['(::var:...::)'] = '/(\(:: *(\w[-\w]*) *:(?!\))\s?)(.*?)(::\))/s';
$PmForm[$bi_BlogForm] = 'form=' .$bi_CoreTemplate .'#blog-form-control fmt=' .$bi_CoreTemplate .'#blog-post-control';
$PmForm[$bi_CommentForm] = 'saveto="' .$bi_CommentGroup .'/{$Group}-{$Name}-' .date('Ymd\THms')
	.'" form=' .$bi_CoreTemplate .'#comment-form-control fmt=' .$bi_CoreTemplate .'#comment-post-control';

# ----------------------------------------
# - Handle Actions
$HandleActions['browse']='bi_HandleBrowse';
SDV($HandleActions['blogitapprove'], 'bi_ApproveComment'); SDV($HandleAuth['blogitapprove'], 'admin');
#SDV($HandleActions['blogitadmin'], 'bi_Admin'); SDV($HandleAuth['blogitadmin'], 'admin');

# ----------------------------------------
# - Markup
# (:blogit [more,intro,list,multiline] options:)text(:blogitend:)
Markup('blogit', 'fulltext', '/\(:blogit (more|intro|list|multiline|substr|tags)\s?(.*?):\)(.*?)\(:blogitend:\)/esi',
	"blogitMU_$1(PSS('$2'), PSS('$3'))");
Markup('includesection', '>if', '/\(:includesection\s+(\S.*?):\)/ei',
	"PRR(bi_includeSection(\$pagename, PSS('$1 '.\$GLOBALS['bi_TemplateList'])))");
Markup('{earlymx(', '>{$var}', '/\{earlymx(\(\w+\b.*?\))\}/e',
	"MarkupExpression(\$pagename, PSS('$1'))");

# Prevent (:e_guibottons:) markup appearing if gui-buttons are not enabled.
if (!$EnableGUIButtons) Markup('e_guibuttons', 'directives','/\(:e_guibuttons:\)/','');

# ----------------------------------------
# - Conditions
$Conditions['bi_ispage'] = 'bi_IsPage($condparm)';
$Conditions['bi_isdate'] = 'bi_IsDate($condparm)';
$Conditions['bi_isemail'] = 'bi_IsEmail($condparm)';

# ----------------------------------------
# - Markup Expressions
# if 0 is null or {$$... then returns 1; if 0 != null then returns ([2] or 0 if 2 is null)
$MarkupExpr['bi_ifnull'] = '( (!empty($args[0]) && substr($args[0],0,3)!=\'{$$\') ?((empty($args[2]) || substr($args[2],0,3)==\'{$$\') ?$args[0] :$args[2]) :$args[1])';
# bi_param "group" "group_val"   Returns: group="group_val" is group_val != "" else returns ""   0:param name; 1:value
$MarkupExpr['bi_param'] = '( (empty($args[1]) || substr($args[1],0,3)==\'{$$\') ?"" :"$args[0]=\"$args[1]\"")';
# bi_cond "!equal" "{$var}" "val" "&&"   Returns: [3] 0 1 2  if 2 != ""; returns "" if "val"="" or substr(1 or 2)="{$$"  Sample Output: && !equal "{$var}" "val"
$MarkupExpr['bi_cond'] = '( (empty($args[2]) || substr($args[2],0,3)==\'{$$\' || substr($args[1],0,3)==\'{$$\') '
	.'?"" : "$args[0] \"$args[1]\" " .($args[0]=="date" ?"" :"\"") .$args[2] .($args[0]=="date" ?"" :"\"") .$args[3])';
$MarkupExpr['bi_base'] = 'bi_BasePage($args[0])';
$MarkupExpr['bi_lt'] = '($args[0]<$args[1]?"true":"false")';
$MarkupExpr['bi_url'] = 'bi_URL($args)';

# ----------------------------------------
# - Main Processing
# ----------------------------------------
$entryType = PageVar($pagename,'$:entrytype');
bi_debugLog('entryType: '.$entryType. '   action: '.$action. '    Target: '.$_POST['target']);
# TODO: Ensure only admin/edit users can perform the actions below
# Allow URL access to sections within $bi_TemplateList, including passed parameters.
if ($action && $action=='blogitadmin' && isset($_GET['s'])){
	$args = bi_Implode($_GET, $p=' ', $s='=', array('n'=>'','action'=>'','s'=>''));
	$GroupHeaderFmt = '(:title '.$_GET['s'].':)(:includesection "#'.$_GET['s']." $args \":)";

# Blog entry being posted from PmForm (new or existing)
}elseif ($action && $action=='pmform'){  #Performed before PmForm action handler.
	$bi_ResetPmFormField = array();
	if ($_POST['target']==$bi_BlogForm){
		# Null out the PostPatterns so that directive markup doesn't get replaced.
		if ($bi_EnablePostDirectives == true)  $PmFormPostPatterns = array();

		# Change field delimiters from (:...:...:) to (::...:...::) for tags and body
		$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/s'] = '(::entrybody:$1::)';  #entrybody MUST be the last variable.
		$ROSPatterns['/\(:pmmarkup:(.*?)(:\))/s'] = '(::pmmarkup:$1::)';	#This field contains [[!tags]]

		$_POST['ptv_bi_version'] = $RecipeInfo['BlogIt']['Version'];  #Prevent spoofing.
		$_POST['ptv_entrytype'] = $bi_PageType['blog'];  #Prevent spoofing.
		$_POST['ptv_pmmarkup'] = bi_SaveTags($_POST['ptv_entrytags'], $_POST['ptv_entrybody'], $bi_TagSeparator);

		# Determine page name from title, replacing ' ' with '-' for seo.
		$MakePageNamePatterns = array(
			"/'/" => '',
			"/[^-[:alnum:]]+/" => '-',	 #"/[^$PageNameChars]+/" => '-',
			'/((^|[^-\\w])\\w)/e' => "strtoupper('$1')",  #'/(.*)/e' => "strtolower('$1')",
			"/\\s+/" => "$bi_TitleSeparator",
			'/--/' => "$bi_TitleSeparator");
		# url will be inherited from title, and will include a group from the url or the default group. If title is blank it is derived from url.
		if (!strpos($_POST['ptv_entryurl'], '.')) $pg = $_POST['ptv_entryurl'];
		else list($gr, $pg) = split('\.',$_POST['ptv_entryurl'],2);
		if (!(empty($pg) && empty($_POST['ptv_entrytitle'])))
			$_POST['ptv_entryurl'] = MakePageName($pagename, (empty($gr)?$bi_DefaultGroup:$gr).'.'.(empty($pg)?$_POST['ptv_entrytitle']:$pg));
		$_POST['title'] = (empty($_POST['ptv_entrytitle'])?$pg:$_POST['ptv_entrytitle']);

		# If valid date, then convert from user entered format to Unix format; otherwise force an error to be triggered in PmForms
		if (bi_IsDate($_POST['ptv_entrydate'])) $_POST['ptv_entrydate'] = strtotime($_POST['ptv_entrydate']);
		else $bi_ResetPmFormField['ptv_entrydate'] =  $_POST['ptv_entrydate'];  #if set, this is used in data-form to override unix timestamp value

	}elseif ($_POST['target']==$bi_CommentForm && $bi_CommentsEnabled=='true'){
		$DefaultPasswords['edit']='';  #Remove edit password to allow initial posting of comment.
		$_POST['ptv_bi_version'] = $RecipeInfo['BlogIt']['Version'];  #Prevent spoofing.
		$_POST['ptv_website'] = (substr($_POST['ptv_website'],0,4)!='http' ?'http://'.$_POST['ptv_website'] :$_POST['ptv_website']);
		$_POST['ptv_entrytype'] = $bi_PageType_Comment;
		$_POST['ptv_commentapproved'] = 'false';
		$_POST['ptv_commentdate'] = $Now;
	}
}else
	bi_AddMarkup();

if ($entryType && $entryType == trim($FmtPV['$bi_PageType_BLOG'],'\'')){
	$GroupHeaderFmt = '(:includesection "#single-entry-view":)';  #Required for action=browse AND comments when redirected on error.
	if ($action=='blogitedit' || ($action=='pmform' && $_POST['target']==$bi_BlogForm)){
		$EnablePostCaptchaRequired = 0;
		$GroupHeaderFmt = '(:includesection "#blog-edit":)';  #Include GroupHeader on blog entry errors, as &action= is overriden by PmForms action.
	}
} elseif ($Group == $bi_CommentGroup && $action=='browse' && CondAuth($pagename, 'admin')){  #After editing/deleting a comment page
	bi_Redirect($pagename);
}

# ----------------------------------------
# - HandleActions Functions
# ----------------------------------------
# If PmForms fails validation, and redirects to a browse, we need to define markup, since it isn't done as part of PmForm handling
# in Main Processing, as markup (tags) isn't processed if markup is defined.
function bi_HandleBrowse($pagename){
	if (isset($GLOBALS['bi_ResetPmFormField'])){
		foreach ($GLOBALS['bi_ResetPmFormField']  as $k => $v) {
			$_POST["$k"]=$v;  #Reset form variables that have errors captured outside the PmForms mechanism
			$GLOBALS['FmtPV']['$bi_Default_'.$k]='"'.$v.'"';  #Always set, but used where values are stored in formats that don't handle errors (like Unix timestamps).
		}
	}
	$GLOBALS['HandleActions']['browse']=$GLOBALS['oldBrowse'];
	bi_AddMarkup();
	HandleDispatch($pagename, 'browse');
}
function bi_ApproveComment($src, $auth='admin') {
	$ap = (isset($GLOBALS['_GET']['pn']) ? $GLOBALS['_GET']['pn'] : '');  #Page to approve
	if ($ap) $old = RetrieveAuthPage($ap,$auth,0, READPAGE_CURRENT);
	if($old){
		$new = $old;
		$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'Approved comment';
		$_POST['diffclass']='minor';
		$new['text'] = preg_replace('/\(:commentapproved:(false):\)/', '(:commentapproved:true:)',$new['text']);
		PostPage($ap,$old,$new);	#Don't need UpdatePage, as we don't require edit functions to run
	}
	bi_Redirect();
}
# http://localhost:4000/pmwiki.php?n=http://localhost:4000/pmwiki.php?n=Site.BlogIt-Admin?action=blogitadmin&s=unapproved-comments&blogid=blog1
function bi_Redirect($src){
# Direct back to the refering page or $src
	if ($src || empty($GLOBALS['_SERVER']['HTTP_REFERER']))
		Redirect(bi_BasePage($src));
	else {
		$r=$GLOBALS['_SERVER']['HTTP_REFERER'];
		header("Location: $r");
		header("Content-type: text/html");
		echo "<html><head>
		<meta http-equiv='Refresh' Content='URL=$r' />
		<title>Redirect</title></head><body></body></html>";
	}
	exit;
}
/*
function bi_Admin($src, $auth='admin') {
	bi_DebugLog('s:'.$GLOBALS['_GET']['s']);
	if (isset($GLOBALS['_GET']['s']))
		$GLOBALS['GroupHeaderFmt'] = '(:includesection "#'.$GLOBALS['$_GET']['s'].'":)';
	HandleDispatch($src, 'browse');
}
*/
# ----------------------------------------
# - Markup Functions
# ----------------------------------------
function blogitMU_more($options, $text){
	return (strpos($text, $GLOBALS['bi_BodyBreak']) !== false ? preg_replace('/{\$FullName}/', $options, $GLOBALS['bi_ReadMore']) : '');
}
function blogitMU_intro($options, $text){
	list($found,$null) = explode($GLOBALS['bi_BodyBreak'], $text);
	return $found;
}
function blogitMU_list($name, $text){
	list($var, $label) = split('/', $text,2);
	$i = count($GLOBALS[$var]);
	foreach ($GLOBALS[$var] as $k => $v)
		$t .= '(:input '. ($i==1?'hidden':'select') .' name=' .$name .' value="' .$k .'" label="' .$v .'" id="' .$var .'":)';
	return ($i==1?'':$label).$t;
}
function blogitMU_multiline($options, $text){
	return preg_replace('/\n/', '<br />', $text);  #Because pmform strips \n, and we end up with comments on a single line.
}
#Markup expression substr doesn't work with multi-line input.
function blogitMU_substr($options, $text){
	list($from, $len) = explode(' ',$options,2);
	$m = min(strpos($text,"\n"),$len);
	return substr($text,$from,empty($m)?$len:$m);
}
function blogitMU_tags($options, $pmmarkup){
	return $pmmarkup;  #currently no need to parse, since only tags are stored here.
}
function bi_includeSection($pagename, $inclspec){
	$args = ParseArgs($inclspec);
	$anc = array_shift($args['']);
	if($anc>'' && $anc{0}!="#") return '';
	foreach($args[''] as $v){
		$x = IncludeText($pagename, "$v$anc");
		if($x>'') return $x;
	}
}

# ----------------------------------------
# - Condition Functions
# ----------------------------------------
function bi_IsPage($pn){
	$mp = MakePageName($GLOBALS['pagename'], $pn);
	if (empty($mp)) return true;
	if ($mp==$GLOBALS['pagename']) return false;
	return PageExists($mp);
}
function bi_IsDate($d){
	$ret = false;
	$re_sep='[\/\-\.]';
	$re_time='( (([0-1]?\d)|(2[0-3])):[0-5]\d)?';
	$re_d='(0?[1-9]|[12][0-9]|3[01])'; $re_m='(0?[1-9]|1[012])'; $re_y='(19\d\d|20\d\d)';
	if (!preg_match('!' .$re_sep .'!',$d)) $d=strftime($GLOBALS['bi_DateEntryFormat'],$d);  #Convert Unix timestamp to EntryFormat
	if (preg_match('!^' .$re_d .$re_sep .$re_m .$re_sep .$re_y. $re_time. '$!',$d,$m))  #dd-mm-yyyy
		$ret = checkdate($m[2], $m[1], $m[3]);
	elseif (preg_match('!^' .$re_y .$re_sep .$re_m .$re_sep .$re_d. $re_time. '$!',$d,$m))  #yyyy-mm-dd
		$ret = checkdate($m[2], $m[3], $m[1]);
	elseif (preg_match('!^' .$re_m .$re_sep .$re_d .$re_sep .$re_y. $re_time. '$!',$d,$m))  #mm-dd-yyyy
		$ret = checkdate($m[1], $m[2], $m[3]);
	return $ret && strtotime($d);
}
function bi_IsEmail($e){
	return (bool)preg_match(
		"/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD"
		,$e);
}

# ----------------------------------------
# - Markup Expression Functions
# ----------------------------------------
function bi_BasePage($pn){
	return preg_replace('/^' .$GLOBALS['bi_CommentGroup'] .'[\/\.](.*?)-(.*?)-\d{8}T\d{6}$/','${1}/${2}',$pn);
}
# 0:fullname 1:param 2:val
function bi_URL($args){
	$GLOBALS['_GET'][$args[1]]=$args[2];
	return $args[0].'?'.bi_Implode($GLOBALS['_GET']);
}

# ----------------------------------------
# - Internal Functions
# ----------------------------------------
function bi_Implode($a, $p='&', $s='=', $ignore=array('n'=>'')){
	foreach($a as $k => $v) if(!isset($ignore[$k])!==false) $o .= $p.$k.$s.$v;
	return substr($o,1);
}
function bi_AddMarkup(){
	Markup('textvar::', '<split', '/\(::\w[-\w]*:(?!\)).*?::\)/s', '');  # Prevent (::...:...:) markup from being displayed.
}
# Combines categories in body [[!...]] with separated tag list in tag-field.
# Stores combined list in tag-field in PmWiki format [[!...]][[!...]].
function bi_SaveTags($user_tags, $body, $sep) {
	# Read tags from body, strip [[!...]]
	$bodyTags = (preg_match_all('/\[\[\!(\w+)\]\]/', $body, $match) ? $match[1] : array());  #array of tags contained in [[!...]] markup.

	# Make sure tag-field entries are in standard separated format, and place in array
	if ($user_tags)  $fieldTags = explode($sep, preg_replace('/'.trim($sep).'\s*/', $sep, $user_tags));

	# Concatenate the tag-field tags, with those in the body,
	$allTags = array_unique(array_merge((array)$fieldTags, (array)$bodyTags));
	sort($allTags);
	#  generate a new separated string.
	return ($allTags?'[[!'.implode(']]'.$sep.'[[!', $allTags).']]':'');
}

# ----------------------------------------
# - General Helper Functions
# ----------------------------------------
function bi_setFmtPV($a){
	foreach ($a as $k)
		$GLOBALS['FmtPV']['$'.$k]='$GLOBALS["'.$k.'"]';
}
# Sets $FmtPV variables named $key_VALUE. $a is an array with the key as the variable name, and values as indecies.
function bi_setFmtPVA ($a){
	foreach ($a as $var=>$vals)
		foreach ($vals as $k=>$v)
			$GLOBALS['FmtPV'][$var .'_' .strtoupper($k)] = "'" .$v ."'";
}
function bi_addPageStore ($n='wikilib.d'){
	$PageStorePath = dirname(__FILE__) ."/$n/{\$FullName}";
	$where = count($GLOBALS['WikiLibDirs']);
	if ($where>1) $where--;
	array_splice($GLOBALS['WikiLibDirs'], $where, 0, array(new PageStore($PageStorePath)));
}
function bi_debugLog ($msg, $out=false){
	if ($out || (!$out && $GLOBALS['BlogIt']['debug']) )
		error_log(date('r'). ' [blogit]: '. $msg);
}
