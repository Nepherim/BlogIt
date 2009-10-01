<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usage instructions refer to: http://pmwiki.com/Cookbook/BlogIt
*/
$RecipeInfo['BlogIt']['Version'] = '2009-10-01';
if ($VersionNum < 2001950)	Abort("<h3>You are running PmWiki version {$Version}. In order to use BlogIt please update to 2.2.1 or later.</h3>");
SDV($BlogIt['debug'],false);
bi_debugLog('====== action: ' .$action .'    Target: ' .$_REQUEST['target'] .'   Save: ' .@$_REQUEST['save']);
#TODO: FPLCountA

# ----------------------------------------
# - Common user settable
SDV($bi_BlogIt_Enabled, 1); if (!IsEnabled($bi_BlogIt_Enabled)) return;
SDV($EnablePostCaptchaRequired, 0);
SDV($bi_DefaultGroup, 'Blog');  #Pre-populates the Pagename field; blogs can exist in *any* group, not simply the default defined here.
SDV($bi_CommentGroup, 'Comments');
SDV($bi_CommentsEnabled, 'true');
SDV($bi_BlogGroups, $bi_DefaultGroup);  #OPTIONAL: Pipe separated list of Blog groups. If you define it then only those groups are searched for entries. If set to null all groups are searched.
SDV($CategoryGroup, 'Tags');
SDV($bi_AuthorGroup, 'Profiles');
SDV($bi_EntriesPerPage, 15);
SDV($bi_LinkToCommentSite, 'true');
SDV($bi_DateEntryFormat, '%d-%m-%Y %H:%M');
SDV($bi_DateDisplayFormat, $TimeFmt);
SDVA($bi_BlogList, array('blog1'=>'blog1'));  #Ensure 'blog1' key remains; you can rename the blog (2nd parameter). Also define other blogs.

# ----------------------------------------
# - Less frequently user settable
SDV($bi_AuthPage, $bi_DefaultGroup .'.' .$DefaultName);  #Need edit/admin users need edit access to this page if not using AuthUser
SDV($bi_GroupFooterFmt, '(:includesection "#tag-pagelist":)(:nl:)');
SDVA($bi_Auth, array('edit'=>array('comment-edit', 'comment-approve', 'blog-edit', 'blog-new', 'sidebar', 'blogit-admin')));  #key: role; value: array of actions
SDV($bi_BodyBreak, XL('break'));
SDV($bi_ReadMore, '%blogit-readmore%[[{$FullName}#' .$bi_BodyBreak .' | $[Read more...]]]');
SDV($bi_CommentSideBarLen, 60);
SDV($bi_TagSeparator, ', ');
SDV($bi_TitleSeparator, '-');
SDV($bi_EnablePostDirectives, true);  #Set to true to allow posting of directives of form (: :) in blog entries.
SDV($bi_StatAction, $TotalCounterAction);  #set by TotalCounter cookbook
SDVA($bi_StatusType, array('draft'=>'draft', 'publish'=>'publish', 'sticky'=>'sticky'));
SDVA($bi_CommentType, array('open'=>'open', 'readonly'=>'read only', 'none'=>'none'));
SDV($bi_NowISOFormat, strftime('%Y%m%d', $Now));  #Used in calls to #blog-summary-pagelist, as part of daterange
SDV($bi_UnstyleFn, '');
SDV($PageNameChars,'-[:alnum:]' .($Charset=='UTF-8' ?'\\x80-\\xfe' :'') );
SDVA($bi_MakePageNamePatterns, array(
	"/'/" => '',														# strip single-quotes
	"/[^". $PageNameChars. "]+/" => $bi_TitleSeparator,	# convert everything else to hyphen
	"/(^\\-+)|(\\-+\$)/" => '',            					# trim hyphens front and back
	"/\\-{2,}/" => $bi_TitleSeparator,							# trim duplicate hyphens
	($Charset=='UTF-8' ?"/^([\\xc0-\\xdf].)/e" :'//') => ($Charset=='UTF-8' ?"utf8toupper('$1')" :''),  # uppercase first letter
	"/^([a-z])/e" => "strtoupper('$1')"
));

# ----------------------------------------
# - Internal Use Only
# ----------------------------------------
SDV($bi_AdminPage, $SiteGroup .'.BlogIt-Admin');
SDV($bi_NewEntryPage, $SiteGroup .'.BlogIt-NewEntry');
SDV($bi_TemplateList, (isset($Skin)?$SiteGroup.'.BlogIt-SkinTemplate-'.$Skin.' ' : '') .$SiteGroup .'.BlogIt-CoreTemplate');
SDVA($bi_PageType, array('blog'=>'blog'));
SDV($bi_PageType_Comment, 'comment');  #Not in PageType list, since we don't want bloggers to be able to select 'comment' types.
SDV($FPLTemplatePageFmt, array(
	'{$FullName}', (isset($Skin)?'{$SiteGroup}.BlogIt-SkinTemplate-'.$Skin : ''), '{$SiteGroup}.BlogIt-CoreTemplate',
	'{$SiteGroup}.LocalTemplates', '{$SiteGroup}.PageListTemplates'));
SDV($bi_CommentPattern, '/^' .$GLOBALS['bi_CommentGroup'] .'[\/\.](.*?)-(.*?)-\d{8}T\d{6}$/');
SDVA($SearchPatterns['blogit-comments'], array('comments' => $bi_CommentPattern));
SDVA($SearchPatterns['blogit'], $bi_BlogGroups>'' ?array('blogit' => '/^(' .$bi_BlogGroups .')\./')
	: array(
		'recent' => '!\.(All)?Recent(Changes|Uploads|' .$bi_CommentGroup .')$!',
		'group' => '!\.Group(Print)?(Header|Footer|Attributes)$!',
		'pmwiki' => '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki)\.!',
		'self' => FmtPageName('!^$FullName$!', $pagename)
));
$bi_BlogForm = 'blogit-entry';
$bi_CommentForm = 'blogit-comments';

# ----------------------------------------
# - Usable on Wiki Pages
bi_setFmtPV(array('bi_BlogIt_Enabled','Now','bi_DefaultGroup','bi_AuthorGroup','bi_CommentsEnabled','CategoryGroup',
	'bi_DateEntryFormat','bi_DateDisplayFormat','bi_BlogForm','bi_CommentForm', 'EnablePostCaptchaRequired',
	'bi_EntriesPerPage','bi_NewEntryPage','bi_AdminPage','bi_LinkToCommentSite','bi_StatAction','bi_NowISOFormat', 'bi_AuthPage', 'bi_PageType_Comment'));
bi_setFmtPVA(array('$bi_StatusType'=>$bi_StatusType, '$bi_CommentType'=>$bi_CommentType,
	'$bi_BlogList'=>$bi_BlogList, '$bi_PageType'=>$bi_PageType));

# ----------------------------------------
# - Cookies: Store the previous page (for returning on Cancel, comments approval, etc)
$LogoutCookies[] = $bi_Cookie.'back-1'; $LogoutCookies[] = $bi_Cookie.'back-2';
if ($action=='pmform' && $_REQUEST['target']==$bi_BlogForm && @$_REQUEST['cancel']>''){  #Cancel button clicked
	$bi_PrevUrl = @$_COOKIE[$bi_Cookie.'back-2']; #need to go back 2, since when in this code we're already moved forward
	bi_Redirect();
	exit;
}
$bi_Params = bi_Implode($_GET);
$bi_CurrUrl = FmtPageName('$PageUrl',$pagename) .(!empty($bi_Params) ?'?'.$bi_Params :'');
$bi_PrevUrl = @$_COOKIE[$bi_Cookie.'back-1'];
if ($bi_CurrUrl!=$bi_PrevUrl){ #don't replace cookies if user is reloading the current page
	setcookie($bi_Cookie.'back-2', $bi_PrevUrl, $Now+60*60*24*30);
	setcookie($bi_Cookie.'back-1', $bi_CurrUrl, $Now+60*60*24*30); #set to current url
}

# ----------------------------------------
# - PmWiki Config
$HandleAuth['source'] = $HandleAuth['diff'] = 'edit';  #[1] Prevent viewing source and diff, primarily for Comments, as this would reveal email.
bi_addPageStore();
$bi_OldAsSpaced_Function = $AsSpacedFunction;
$AsSpacedFunction = 'AsSpacedHyphens';  #[1]

# ----------------------------------------
# - PmForms Setup
$PmFormTemplatesFmt = (isset($PmFormTemplatesFmt) ?$PmFormTemplatesFmt :array());
array_unshift ($PmFormTemplatesFmt,	(isset($Skin) ?'{$SiteGroup}.BlogIt-SkinTemplate-'.$Skin : ''), '{$SiteGroup}.BlogIt-CoreTemplate');
SDV($bi_Cookie, $CookiePrefix.'blogit-');
include_once($FarmD.'/cookbook/pmform.php');
$PmForm[$bi_BlogForm] = 'form=#blog-form-control fmt=#blog-post-control';
$PmForm[$bi_CommentForm] = 'saveto="' .$bi_CommentGroup .'.{$Group}-{$Name}-' .date('Ymd\THms')
	.'" form=#comment-form-control fmt=#comment-post-control';

# ----------------------------------------
# - Handle Actions
$bi_OldHandleActions = $HandleActions;
$HandleActions['pmform']='bi_HandleProcessForm';
$HandleActions['browse']='bi_HandleBrowse';
#TODO: SDV($HandleActions['approvsites'],'bi_HandleApprove');  #approveurl
SDV($HandleActions['blogitadmin'], 'bi_HandleAdmin'); SDV($HandleAuth['blogitadmin'], 'blogit-admin');
SDV($HandleActions['blogitapprove'], 'bi_HandleApproveComment'); SDV($HandleAuth['blogitapprove'], 'comment-approve');
#TODO: SDV($HandleActions['blogitedit'], 'bi_HandleEdit'); SDV($HandleAuth['blogitedit'], 'blog-edit');

# ----------------------------------------
# - Authentication
SDV($AuthFunction,'PmWikiAuth');
$bi_AuthFunction = $AuthFunction;
$AuthFunction = 'bi_BlogItAuth';

# Need to save entrybody in an alternate format (::entrybody:...::), to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['(::var:...::)'] = '/(\(:: *(\w[-\w]*) *:(?!\))\s?)(.*?)(::\))/s'; #[1]
# PageVar MUST be after PageTextVarPatterns declaration, otherwise on single-entry read, body is NULL.
$bi_EntryType = PageVar($pagename,'$:entrytype');
$bi_EntryStatus = PageVar($pagename,'$:entrystatus');
list($Group, $Name) = explode('.', ResolvePageName($pagename));
if ( (isset($bi_EntryType)||$pagename==$bi_NewEntryPage) && bi_Auth('*') ) $EnablePostCaptchaRequired = 0;
bi_debugLog('entryType: '.$bi_EntryType.' status: '.$bi_EntryStatus);

# ----------------------------------------
# - Pagination
$FmtPV['$bi_PageNext'] = (isset($_GET['page']) ? $_GET['page']+1 : 2);
$FmtPV['$bi_PagePrev'] = (isset($_GET['page']) && ($_GET['page']>0) ? $_GET['page']-1 : 0);
$FmtPV['$bi_EntryStart'] = (($FmtPV['$bi_PageNext']-2) * (isset($_GET['count']) ?$_GET['count'] :$bi_EntriesPerPage)) + 1;
$FmtPV['$bi_EntryEnd']   = $FmtPV['$bi_EntryStart'] + (isset($_GET['count']) ?$_GET['count'] :$bi_EntriesPerPage) - 1;

# ----------------------------------------
# - Default Skin
if (!isset($Skin) || $Skin=='pmwiki')  $HTMLStylesFmt['bi-pmwiki'] .=
	'#wikiedit .inputbutton{margin:2px;} .blogit-listmore{text-align:right;} .blogit-next-entries, .blogit-previous-entries{padding-right: 5px;} ';

# ----------------------------------------
# - Categories
# Doesn't pick up categories defined as page variables.
$LinkCategoryFmt = "<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>"; #[1]

# ----------------------------------------
# - Markup
# (:blogit [more|intro|list|multiline|cleantext|tags] options:)text(:blogitend:)
Markup('blogit', 'fulltext', '/\(:blogit (more|intro|list|multiline|cleantext|tags)\s?(.*?):\)(.*?)\(:blogitend:\)/esi',
	"blogitMU_$1(PSS('$2'), PSS('$3'))");
Markup('includesection', '>if', '/\(:includesection\s+(\S.*?):\)/ei',
	"PRR(bi_includeSection(\$pagename, PSS('$1 '.\$GLOBALS['bi_TemplateList'])))");
if (IsEnabled($EnableGUIButtons)){
	if ($bi_EntryType == trim($FmtPV['$bi_PageType_BLOG'],'\'') || $pagename == $bi_NewEntryPage)
		include_once("$FarmD/scripts/guiedit.php");  #PmWiki only includes this automatically if action=edit.
}else Markup('e_guibuttons', 'directives','/\(:e_guibuttons:\)/','');  #Prevent (:e_guibuttons:) markup appearing if guiedit not enabled.

# ----------------------------------------
# - Conditions
$Conditions['bi_ispage'] = 'bi_IsPage($condparm)';
$Conditions['bi_isdate'] = 'bi_IsDate($condparm)';
$Conditions['bi_isemail'] = 'bi_IsEmail($condparm)';
$Conditions['bi_auth'] = 'bi_Auth($condparm)';
$Conditions['bi_isnull'] = 'bi_IsNull($condparm)==""';
$Conditions['bi_lt'] = '($args[0]<$args[1] ?true :false)';

# ----------------------------------------
# - Markup Expressions
# if [0] is null or {$... then returns [1]; if [0] != null then returns ([2] or [0] if [2] is null)
$MarkupExpr['bi_ifnull'] = '( bi_IsNull($args[0])!="" ?( bi_IsNull($args[2])=="" ?$args[0] :$args[2]) :$args[1])';
$MarkupExpr['bi_encode'] = 'htmlentities(bi_IsNull($args[0]),ENT_QUOTES)';
# bi_param "group" "group_val"   Returns: group="group_val" is group_val != "" else returns ""   0:param name; 1:value
$MarkupExpr['bi_param'] = '( bi_IsNull($args[1])=="" ?"" :"$args[0]=\"$args[1]\"")';
$MarkupExpr['bi_base'] = 'bi_BasePage($args[0])';
$MarkupExpr['bi_url'] = 'bi_URL($args)';
$MarkupExpr['bi_default_url'] = '($args[0]=="' .$bi_NewEntryPage .'" ?"' .$bi_DefaultGroup .'." :$args[0])';

# ----------------------------------------
# - Set GroupHeaderFmt and Footer
if (@$bi_EntryType == trim($FmtPV['$bi_PageType_BLOG'],'\'')){
	if ( (($action=='blogitedit' || ($action=='pmform' && $_REQUEST['target']==$bi_BlogForm)) && bi_Auth('blog-edit')) )
		$GroupHeaderFmt .= '(:includesection "#blog-edit":)';  #Include GroupHeader on blog entry errors, as &action= is overriden by PmForms action.
	elseif ($bi_EntryStatus!=$bi_StatusType['draft'] || ($bi_EntryStatus==$bi_StatusType['draft'] && bi_Auth('blog-edit,blog-new,blogit-admin')) )
		$GroupHeaderFmt .= '(:includesection "#single-entry-view":)';  #Required for action=browse AND comments when redirected on error (in which case $action=pmform).
	if ($action=='print')  $GroupPrintHeaderFmt .= $GroupHeaderFmt;
}
elseif ($Group == $CategoryGroup)  $GroupHeaderFmt .= '(:title '.$AsSpacedFunction(PageVar($pagename, '$Name')).':)';
if ($Group == $CategoryGroup) $GroupFooterFmt .= $bi_GroupFooterFmt;

# ----------------------------------------
# - HandleActions Functions
# ----------------------------------------
# If PmForms fails validation, and redirects to a browse, we need to define markup, since it isn't done as part of PmForm handling
# in Main Processing, as markup (tags) isn't processed if markup is defined.
function bi_HandleBrowse($pagename){
global $_REQUEST, $bi_ResetPmFormField, $FmtPV, $HandleActions, $bi_OldHandleActions, $Group, $bi_CommentGroup;
	if ($Group == $bi_CommentGroup && bi_Auth('comment-edit')){  #After editing/deleting a comment page
		bi_Redirect($pagename); return;
	} elseif (isset($bi_ResetPmFormField))
		foreach ($bi_ResetPmFormField  as $k => $v) {
			$_REQUEST["$k"]=$v;  #Reset form variables that have errors captured outside the PmForms mechanism
			$FmtPV['$bi_Default_'.$k]='"'.$v.'"';  #Always set, but used where values are stored in formats that don't handle errors (like Unix timestamps).
		}
	$HandleActions['browse']=$bi_OldHandleActions['browse'];
	bi_AddMarkup();
	HandleDispatch($pagename, 'browse');
}
#TODO
function bi_HandleApprove($pagename){
	$GLOBALS['HandleActions']['approvesites']=$GLOBALS['oldUrlApprove'];
#	HandleDispatch($pagename, 'browse');
}
function bi_HandleApproveComment($src, $auth='comment-approve'){
global $_REQUEST, $_POST, $Now, $ChangeSummary;
	if (bi_Auth($auth)){
		$ap = @$_REQUEST['pn'];
		if ($ap) $old = RetrieveAuthPage($ap,'read',0, READPAGE_CURRENT);
		if($old){
			$new = $old;
			$new['csum'] = $new['csum:' .$Now] = $ChangeSummary = 'Approved comment';
			$_POST['diffclass']='minor';
			$new['text'] = preg_replace('/\(:commentapproved:false:\)/', '(:commentapproved:true:)',$new['text']);
			PostPage($ap,$old,$new);  #Don't need UpdatePage, as we don't require edit functions to run
		}
	}
	bi_Redirect();
}
# Allow URL access to sections within $bi_TemplateList, including passed parameters.
function bi_HandleAdmin($src, $auth='blogit-admin'){
global $_REQUEST, $GroupHeaderFmt;
	if (bi_Auth($auth)){
		if (isset($_REQUEST['s'])){
			$args = bi_Implode($_REQUEST, ' ', '=', array('n'=>'','action'=>'','s'=>''));
			$GroupHeaderFmt .= '(:title $[' .$_REQUEST['s'] .']:)(:includesection "#' .$_REQUEST['s'] ." $args \":)";
		}
	}
	HandleDispatch($src, 'browse');
}
function bi_HandleProcessForm ($src, $auth='read'){
global $bi_ResetPmFormField, $_POST, $RecipeInfo, $bi_BlogForm, $bi_EnablePostDirectives, $PmFormPostPatterns, $ROSPatterns, $bi_PageType,$CategoryGroup,$bi_StatusType,
	$pagename, $bi_DefaultGroup, $bi_TagSeparator, $bi_CommentsEnabled, $bi_CommentForm, $bi_PageType_Comment, $Now, $bi_OldHandleActions, $EnablePost, $AutoCreate;

	$bi_ResetPmFormField = array();
	$_POST['ptv_bi_version'] = $RecipeInfo['BlogIt']['Version'];  #Prevent spoofing.
	if (@$_POST['target']==$bi_BlogForm && @$_POST['save']>''){
		if ( $_POST['ptv_entrystatus']!=$bi_StatusType['draft'] )  $AutoCreate['/^' .$CategoryGroup .'\./'] = array('ctime' => $Now);
		if ($bi_EnablePostDirectives) $PmFormPostPatterns = array();  # Null out the PostPatterns so that directive markup doesn't get replaced.

		# Change field delimiters from (:...:...:) to (::...:...::) for tags and body
		$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/s'] = '(::entrybody:$1::)';  #entrybody MUST be the last variable.
		$ROSPatterns['/\(:pmmarkup:(.*?)(\(:title .*?:\)):\)/s'] = '(::pmmarkup:$1$2::)';  #This field contains (:TITLE:), so need to find .*?:)

		# Determine page name from title, replacing ' ' with '-' for seo.
		bi_setMakePageNamePatterns();

		# url will be inherited from title, and will include a group from the url or the default group. If title is blank it is derived from url.
		if (!strpos($_POST['ptv_entryurl'], '.'))  $pg = $_POST['ptv_entryurl'];
		else  list($gr, $pg) = explode('.',$_POST['ptv_entryurl']);

		# If valid date, then convert from user entered format to Unix format; otherwise force an error to be triggered in PmForms
		# NB: If page subsequently fails to post (due to incorrect p/w or captcha) then entrydate is already in unix time format.
		if (bi_IsDate($_POST['ptv_entrydate'])){
			if (!preg_match('!\d{5,}!',$_POST['ptv_entrydate']))  $_POST['ptv_entrydate'] = strtotime($_POST['ptv_entrydate']);
		}else  $bi_ResetPmFormField['ptv_entrydate'] =  $_POST['ptv_entrydate'];  #if set, this is used in data-form to override unix timestamp value

		$_POST['ptv_entrytype'] = $bi_PageType['blog'];  #Prevent spoofing.
		$_POST['ptv_entrytitle'] = (empty($_POST['ptv_entrytitle']) ?$pg :$_POST['ptv_entrytitle']);
		$_POST['ptv_entryurl'] = MakePageName($pagename, ( empty($gr) ?$bi_DefaultGroup :$gr ) .'.' .( empty($pg) ?$_POST['ptv_entrytitle'] :$pg) );
		$_POST['ptv_pmmarkup'] = bi_SaveTags($_POST['ptv_entrybody'], $_POST['ptv_entrytags'], $bi_TagSeparator) .'(:title ' .$_POST['ptv_entrytitle'] .':)';

	}elseif ($bi_CommentsEnabled=='true' && @$_POST['target']==$bi_CommentForm){
		$_POST['ptv_entrytype'] = $bi_PageType_Comment;
		$_POST['ptv_website'] = (!empty($_POST['ptv_website']) && substr($_POST['ptv_website'],0,4)!='http' ?'http://'.$_POST['ptv_website'] :$_POST['ptv_website']);
		$_POST['ptv_commentapproved'] = 'false';
		$_POST['ptv_commentdate'] = $Now;
	}
	$bi_OldHandleActions['pmform']($src, $auth);
}

# ----------------------------------------
# - Markup Functions
# ----------------------------------------
function blogitMU_more($options, $text){
	return (strpos($text, '[[#' .$GLOBALS['bi_BodyBreak'] .']]') !== false ? preg_replace('/{\$FullName}/', $options, $GLOBALS['bi_ReadMore']) : '');
}
function blogitMU_intro($options, $text){
	list($found,$null) = explode('[[#' .$GLOBALS['bi_BodyBreak'] .']]', $text);
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
# options is the length of the string, or use $bi_CommentSideBarLen is empty
function blogitMU_cleantext($options, $text){
global $bi_CommentSideBarLen, $pagename, $bi_UnstyleFn;
	$m = min(strpos($text, "\n"), (!empty($options) ?$options :$bi_CommentSideBarLen));
	return (empty($bi_UnstyleFn)
		?substr($text, 0, empty($m) ?$bi_CommentSideBarLen :$m)
		:$bi_UnstyleFn($pagename, substr($text, 0, empty($m) ?$bi_CommentSideBarLen :$m))
	);
}
function blogitMU_tags($options, $tags){
	return bi_SaveTags('', $tags, $GLOBALS['bi_TagSeparator']);
}
function bi_includeSection($pagename, $inclspec){
	$args = ParseArgs($inclspec);
	$anc = array_shift($args['']);
	if($anc>'' && $anc{0}!="#")  return '';
	foreach($args[''] as $v){
		$x = IncludeText($pagename, "$v$anc");
		if($x>'')  return $x;
	}
}

# ----------------------------------------
# - Condition Functions
# ----------------------------------------
function bi_IsPage($pn){
global $pagename;
	$mp = MakePageName($pagename, $pn);
	if (empty($mp))  return true;
	if ($mp==$pagename)  return false;
	return PageExists($mp);
}
function bi_IsDate($d){
	if (empty($d)) return true; #false causes two date invalid messages.
	$re_sep='[\/\-\.]';
	$re_time='( (([0-1]?\d)|(2[0-3])):[0-5]\d)?';
	$re_d='(0?[1-9]|[12][0-9]|3[01])'; $re_m='(0?[1-9]|1[012])'; $re_y='(19\d\d|20\d\d)';
	if (preg_match('!\d{5,}!',$d)) $d=strftime($GLOBALS['bi_DateEntryFormat'],$d);  #Convert Unix timestamp to EntryFormat
	if (preg_match('!^' .$re_d .$re_sep .$re_m .$re_sep .$re_y. $re_time. '$!',$d,$m))  #dd-mm-yyyy
		$ret = checkdate($m[2], $m[1], $m[3]);
	elseif (preg_match('!^' .$re_y .$re_sep .$re_m .$re_sep .$re_d. $re_time. '$!',$d,$m))  #yyyy-mm-dd
		$ret = checkdate($m[2], $m[3], $m[1]);
	elseif (preg_match('!^' .$re_m .$re_sep .$re_d .$re_sep .$re_y. $re_time. '$!',$d,$m))  #mm-dd-yyyy
		$ret = checkdate($m[1], $m[2], $m[3]);
	else $ret = false;
	return $ret;
}
function bi_IsEmail($e){
	return (bool)preg_match(
		"/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD"
		,$e);
}
function bi_IsNull($e){
	return (!empty($e) && substr($e,0,3)!='{*$' && substr($e,0,2)!='{$' && substr($e,0,3)!='{=$' ?$e :'');
}

# ----------------------------------------
# - Markup Expression Functions
# ----------------------------------------
function bi_BasePage($pn){
	return preg_replace($GLOBALS['bi_CommentPattern'],'${1}.${2}',$pn);
}
# 0:fullname 1:param 2:val
function bi_URL($args){
global $_GET;
	$_GET[$args[1]]=$args[2];
	return $args[0].'?'.bi_Implode($_GET);
}

# ----------------------------------------
# - Authentication Functions
# ----------------------------------------
function bi_BlogItAuth($pagename, $level, $authprompt=true, $since=0) {
global $action, $bi_AuthFunction, $bi_CommentsEnabled, $bi_CommentGroup, $bi_EntryType, $FmtPV;
	# Set level to read if a non-authenticated user is posting a comment.
	if ( (($level=='edit') || ($level=='publish'))
		&& $action=='pmform' && $bi_EntryType == trim($FmtPV['$bi_PageType_BLOG'],'\'')
		&& IsEnabled($bi_CommentsEnabled,1) && preg_match("/^" .$bi_CommentGroup ."\./", $pagename) ){
		$level = 'read';
		$authprompt = false;
	}
	return $bi_AuthFunction($pagename, $level, $authprompt, $since);
}
# Called as part of markup condition. Determine whether the current user is authorized for an action
function bi_Auth($condparm){  #condparm: comma separated list of actions, and optional space separated pagename -- "blog-new,blog-edit Blog.This-entry"
global $AuthList, $bi_Auth, $pagename, $EnableAuthUser, $bi_AuthPage, $bi_AuthFunction, $bi_AdminPage, $bi_NewEntryPage;
	@list($action, $pn) = explode(' ', $condparm, 2);
	$action=explode(',', trim($action,'\'"'));
	if (!IsEnabled($EnableAuthUser))
		$pn = ( in_array('*',$action)	|| in_array('sidebar',$action)
			||($pagename==$bi_AdminPage && in_array('blogit-admin',$action))
			||($pagename==$bi_NewEntryPage && in_array('blog-new',$action)) )
			?$bi_AuthPage
			:(isset($pn)) ?$pn :$pagename;

	foreach ($action as $a){
		foreach ($bi_Auth as $role => $action_list){
			if ( $a=='*' || in_array($a, $action_list) ){  #Is the action assigned to a role?
				if ( (IsEnabled($EnableAuthUser) && $AuthList['@'.$role] > 0)  #the user is assigned to this role
					|| (!IsEnabled($EnableAuthUser) && $bi_AuthFunction($pn, $role, false, READPAGE_CURRENT)) )  #the user has these role privs on this page
					return true;
	}}}
	return false;
}

# ----------------------------------------
# - Internal Functions
# ----------------------------------------
# Direct back to the refering page or $src
function bi_Redirect($src=''){
global $bi_PrevUrl;
	$r = (!empty($src) ?FmtPageName('$PageUrl', bi_BasePage($src)) :$bi_PrevUrl);
	header("Location: $r");
	header("Content-type: text/html");
	echo "<html><head>
	<meta http-equiv='Refresh' Content='URL=$r' />
	<title>Redirect</title></head><body></body></html>";
	exit;
}
# Used to create a URL parameter string from an array, removing ?n= parameter.
function bi_Implode($a, $p='&', $s='=', $ignore=array('n'=>'')){
	foreach($a as $k => $v)  if(!isset($ignore[$k])!==false) $o .= $p.$k.$s.$v;
	return substr($o,1);
}
function bi_AddMarkup(){
	Markup('textvar::', '<split', '/\(::\w[-\w]*:(?!\)).*?::\)/s', '');  # Prevent (::...:...:) markup from being displayed.
}
# Combines categories in body [[!...]] with separated tag list in tag-field.
# Stores combined list in tag-field in PmWiki format [[!...]][[!...]].
function bi_SaveTags($body, $user_tags, $sep) {
global $pagename;
	bi_setMakePageNamePatterns();
	# Read tags from body, strip [[!...]]
	if ($body)  $bodyTags = (preg_match_all('/\[\[\!(\w+)\]\]/', $body, $match) ? $match[1] : array());  #array of tags contained in [[!...]] markup.

	# Make sure tag-field entries are in standard separated format, and place in array
	if ($user_tags) $fieldTags = explode($sep, preg_replace('/'.trim($sep).'\s*/', $sep, $user_tags));
	# Concatenate the tag-field tags, with those in the body,
	$allTags = array_unique(array_merge((array)$fieldTags, (array)$bodyTags));
	sort($allTags);

	# generate a new separated string.
	return ($allTags ?'[[!'.implode(']]'.$sep.'[[!', $allTags).']]' :'');
}
function bi_setMakePageNamePatterns(){
global $MakePageNamePatterns, $bi_MakePageNamePatterns;
	$MakePageNamePatterns = array_merge(
		(isset($MakePageNamePatterns) ?$MakePageNamePatterns :array()),	# merge with prior patterns (perhaps ISO char patterns)
		$bi_MakePageNamePatterns
	);
}
function AsSpacedHyphens($text) {
global $bi_OldAsSpaced_Function, $bi_EntryType, $Group, $CategoryGroup;
	if ($Group == $CategoryGroup || isset($bi_EntryType))  return (strtr($bi_OldAsSpaced_Function($text),'-',' '));
	else  return ($bi_OldAsSpaced_Function($text));
}

# ----------------------------------------
# - General Helper Functions
# ----------------------------------------
function bi_setFmtPV($a){
	foreach ($a as $k)  $GLOBALS['FmtPV']['$'.$k]='$GLOBALS["'.$k.'"]';
}
# Sets $FmtPV variables named $key_VALUE. $a is an array with the key as the variable name, and values as indecies.
function bi_setFmtPVA ($a){
	foreach ($a as $var=>$vals)
		foreach ($vals as $k=>$v)
			$GLOBALS['FmtPV'][$var .'_' .strtoupper($k)] = "'" .$v ."'";
}
function bi_addPageStore ($n='wikilib.d'){
global $WikiLibDirs;
	$PageStorePath = dirname(__FILE__) ."/$n/{\$FullName}";
	$where = count($WikiLibDirs);
	if ($where>1) $where--;
	array_splice($WikiLibDirs, $where, 0, array(new PageStore($PageStorePath)));
}
function bi_debugLog ($msg, $out=false){
	if ($out || (!$out && $GLOBALS['BlogIt']['debug']) )  error_log(date('r'). ' [blogit]: '. $msg);
}
