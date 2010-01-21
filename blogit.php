<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usage instructions refer to: http://pmwiki.com/wiki/Cookbook/BlogIt
*/
$RecipeInfo['BlogIt']['Version'] = '2010-18-1';
if ($VersionNum < 2001950)	Abort("<h3>You are running PmWiki version {$Version}. In order to use BlogIt please update to 2.2.1 or later.</h3>");

# ----------------------------------------
# - User settings
SDV($bi_BlogIt_Enabled, 1); if (!IsEnabled($bi_BlogIt_Enabled)) return;
SDV($EnablePostCaptchaRequired, 0);
SDV($bi_DefaultGroup, 'Blog');  #Pre-populates the Pagename field; blogs can exist in *any* group, not simply the default defined here.
SDV($bi_BlogGroups, $bi_DefaultGroup);  #OPTIONAL: Pipe separated list of Blog groups. If you define it then only those groups are searched for entries. If set to null all groups are searched.
SDV($CategoryGroup, 'Tags');  #[1]
SDV($bi_AuthorGroup, 'Profiles');
SDV($bi_CommentGroup, 'Comments');
SDV($bi_CommentsEnabled, 'true');
SDV($bi_DefaultCommentStatus, 'true');
SDV($bi_LinkToCommentSite, 'true');
SDV($bi_EntriesPerPage, 15);
SDV($bi_DisplayFuture, 'false');
SDVA($bi_BlogList, array('blog1'));  #Ensure 'blog1' key remains; you can add other blogs.
SDVA($bi_Auth, array('edit'=>array('comment-edit', 'comment-approve', 'blog-edit', 'blog-new', 'sidebar', 'blogit-admin')));  #key: role; value: array of actions

# ----------------------------------------
# - Advanced user settings
SDV($bi_AuthPage, $bi_DefaultGroup .'.' .$DefaultName);  #edit/admin users need edit access to this page if not using AuthUser
SDV($bi_GroupFooterFmt, '(:includesection "#tag-pagelist":)(:nl:)');  #use to show all pages in a specific category when browsing a Tag group
SDV($bi_CommentSideBarLen, 60);
SDV($bi_TagSeparator, ', ');
SDV($bi_TitleSeparator, '-');
SDV($bi_EnablePostDirectives, true);  #Set to true to allow posting of directives of form (: :) in blog entries.
SDV($bi_StatAction, $TotalCounterAction);  #set by TotalCounter cookbook
SDV($bi_Cookie, $CookiePrefix.'blogit-');
SDV($bi_UnstyleFn, '');
SDVA($bi_StatusType, array('draft', 'publish', 'sticky'));
SDVA($bi_CommentType, array('open', 'readonly', 'none'));
SDV($PageNameChars,'-[:alnum:]' .($Charset=='UTF-8' ?'\\x80-\\xfe' :'') );
SDVA($bi_MakePageNamePatterns, array(
	"/'/" => '',														# strip single-quotes
	"/[^". $PageNameChars. "]+/" => $bi_TitleSeparator,	# convert everything else to hyphen
	"/(^\\" .$bi_TitleSeparator ."+)|(\\" .$bi_TitleSeparator ."+\$)/" => '',            					# trim hyphens front and back
	"/\\" .$bi_TitleSeparator ."{2,}/" => $bi_TitleSeparator,							# trim duplicate hyphens
	($Charset=='UTF-8' ?"/^([\\xc0-\\xdf].)/e" :'//') => ($Charset=='UTF-8' ?"utf8toupper('$1')" :''),  # uppercase first letter
	"/^([a-z])/e" => "strtoupper('$1')"
));
SDVA($bi_FixPageTitlePatterns, array(
	'/[.\\/#]/' => ''	#remove dots, forward and backslashes in page titles as MakePageName returns '' when these characters are present
));
SDVA($bi_Paths,array('pmform'=>"$FarmD/cookbook/pmform.php", 'guiedit'=>"$FarmD/scripts/guiedit.php", 'convert'=>"$FarmD/cookbook/blogit/blogit_upgrade.php"));

# ----------------------------------------
# - Internal Use Only
# ----------------------------------------
SDV($BlogIt['debug'],false); bi_debugLog('====== action: ' .$action .'    Target: ' .$_REQUEST['target'] .'   Save: ' .@$_REQUEST['save']);
SDV($bi_AdminPage, $SiteGroup .'.BlogIt-Admin');
SDV($bi_NewEntryPage, $SiteGroup .'.BlogIt-NewEntry');
SDV($bi_BlogListPage, $SiteGroup .'.BlogList');
SDV($bi_TemplateList, (isset($Skin)?$SiteGroup.'.BlogIt-SkinTemplate-'.$Skin.' ' : '') .$SiteGroup .'.BlogIt-CoreTemplate');
SDVA($bi_PageType, array('blog'));  #Comment is not in PageType list, since we don't want bloggers to be able to select 'comment' types.
SDV($FPLTemplatePageFmt, array(
	'{$FullName}', (isset($Skin)?'{$SiteGroup}.BlogIt-SkinTemplate-'.$Skin : ''), '{$SiteGroup}.BlogIt-CoreTemplate',
	'{$SiteGroup}.LocalTemplates', '{$SiteGroup}.PageListTemplates'));
SDV($bi_CommentPattern, '/^' .$bi_CommentGroup .'[\/\.](.*?)-(.*?)-(\d{8}T\d{6}){1}\z/');
SDVA($SearchPatterns['blogit-comments'], array('comments' => $bi_CommentPattern));
SDVA($SearchPatterns['blogit'], ($bi_BlogGroups>''
	?array('blogit' => '/^(' .$bi_BlogGroups .')\./')
	:array(
		'recent' => '!\.(All)?Recent(Changes|Uploads|' .$bi_CommentGroup .')$!',
		'group' => '!\.Group(Print)?(Header|Footer|Attributes)$!',
		'pmwiki' => '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki)\.!',
		'self' => FmtPageName('!^$FullName$!', $pagename)
)));
$bi_BlogForm = 'blogit-entry';
$bi_CommentForm = 'blogit-comments';

# ----------------------------------------
# - Usable on Wiki Pages
bi_setFmtPV(array('bi_BlogIt_Enabled','bi_DefaultGroup','bi_CommentsEnabled','CategoryGroup','Now','bi_BlogForm','bi_CommentForm','bi_CommentGroup',
	'EnablePostCaptchaRequired','bi_DisplayFuture','bi_EntriesPerPage','bi_NewEntryPage','bi_AdminPage','bi_StatAction','bi_AuthPage'));

# ----------------------------------------
# - PmWiki Config
$HandleAuth['source'] = $HandleAuth['diff'] = 'edit';  #[1] Prevent viewing source and diff, primarily for Comments, as this would reveal email.
bi_addPageStore();
$bi_OldAsSpaced_Function = $AsSpacedFunction;
$AsSpacedFunction = 'AsSpacedHyphens';  #[1]
# Doesn't pick up categories defined as page variables.
$LinkCategoryFmt = "<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>"; #[1]

# ----------------------------------------
# - PmForms Setup
include_once($bi_Paths['pmform']);
$PmFormTemplatesFmt = (isset($PmFormTemplatesFmt) ?$PmFormTemplatesFmt :array());
array_unshift ($PmFormTemplatesFmt,	(isset($Skin) ?'{$SiteGroup}.BlogIt-SkinTemplate-'.$Skin : ''), '{$SiteGroup}.BlogIt-CoreTemplate');
$PmForm[$bi_BlogForm] = 'form=#blog-form-control fmt=#blog-post-control';
$PmForm[$bi_CommentForm] = 'saveto="' .$bi_CommentGroup .'.{$Group}-{$Name}-' .date('Ymd\THms')
	.'" form=#comment-form-control fmt=#comment-post-control';

# ----------------------------------------
# - Handle Actions
$bi_OldHandleActions = $HandleActions;
$HandleActions['pmform']='bi_HandleProcessForm';
$HandleActions['browse']='bi_HandleBrowse';
#TODO: SDV($HandleActions['approvsites'],'bi_HandleUrlApprove');  #approveurl
SDV($HandleActions['blogitadmin'], 'bi_HandleAdmin'); SDV($HandleAuth['blogitadmin'], 'blogit-admin');
SDV($HandleActions['blogitapprove'], 'bi_HandleApproveComment'); SDV($HandleAuth['blogitapprove'], 'comment-approve');
SDV($HandleActions['blogitunapprove'], 'bi_HandleUnapproveComment'); SDV($HandleAuth['blogitunapprove'], 'comment-approve');
SDV($HandleActions['blogitcommentdelete'], 'bi_HandleDeleteComment'); SDV($HandleAuth['blogitcommentdelete'], 'comment-edit');
SDV($HandleActions['blogitupgrade'], 'bi_HandleUpgrade'); SDV($HandleAuth['blogitupgrade'], 'admin');
# Cannot be done as part of handler due to scoping issues when include done in function
if ($action=='blogitupgrade')  include_once($bi_Paths['convert']);

# ----------------------------------------
# - Authentication
SDV($AuthFunction,'PmWikiAuth');
$bi_AuthFunction = $AuthFunction;
$AuthFunction = 'bi_BlogItAuth';

# Need to save entrybody in an alternate format to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['[[#anchor]]'] = '/(\[\[#blogit_(\w[_-\w]*)\]\](?: *\n)?)(.*?)(\[\[#blogit_\2end\]\])/s';

$pagename = ResolvePageName($pagename);  #undo clean urls (replace / with .) to make pagename checks easier
if ($pagename == $bi_BlogListPage)	$FmtPV['$bi_BlogId']='"'.htmlentities(stripmagic($_GET['blogid'])).'"';
$bi_EntryType = PageTextVar($pagename,'entrytype');  #PageVar MUST be after PageTextVarPatterns declaration, otherwise on single-entry read, body is NULL.
bi_debugLog('entryType: '.$bi_EntryType);
list($Group, $Name) = explode('.', $pagename);
if ( (isset($bi_EntryType)||$pagename==$bi_AdminPage||$pagename==$bi_NewEntryPage) && bi_Auth('*') ){  #TODO: put blogit pages in array
	$EnablePostCaptchaRequired = 0;
	# Cookies: Store the previous page (for returning on Cancel, comments approval, etc)
	$LogoutCookies[] = $bi_Cookie.'back-1'; $LogoutCookies[] = $bi_Cookie.'back-2';
	if ( ( ($action=='pmform' && $_REQUEST['target']==$bi_BlogForm) || ($action=='edit') ) && @$_POST['cancel']){  #Cancel button clicked
		$bi_PrevUrl = @$_COOKIE[$bi_Cookie.'back-2']; #need to go back 2, since when in this code we're already moved forward
		bi_Redirect();
		exit;
	}
	$bi_Params = bi_Implode($_GET);
	$bi_CurrUrl = $pagename .(!empty($bi_Params) ?'?'.$bi_Params :'');
	$bi_PrevUrl = @$_COOKIE[$bi_Cookie.'back-1'];
	if ($bi_CurrUrl!=$bi_PrevUrl){  #don't replace cookies if user is reloading the current page
		setcookie($bi_Cookie.'back-2', $bi_PrevUrl, 0, '/');
		setcookie($bi_Cookie.'back-1', $bi_CurrUrl, 0, '/'); #set to current url
	}
}

# ----------------------------------------
# - Pagination
$FmtPV['$bi_PageNext'] = (isset($_GET['page']) ?$_GET['page']+1 :2);
$FmtPV['$bi_PagePrev'] = (isset($_GET['page']) && ($_GET['page']>0) ?$_GET['page']-1 :0);
$FmtPV['$bi_EntryStart'] = (($FmtPV['$bi_PageNext']-2) * (isset($_GET['count']) ?$_GET['count'] :$bi_EntriesPerPage)) + 1;
$FmtPV['$bi_EntryEnd']   = $FmtPV['$bi_EntryStart'] + (isset($_GET['count']) ?$_GET['count'] :$bi_EntriesPerPage) - 1;

# ----------------------------------------
# - Markup
Markup('blogit', 'fulltext', '/\(:blogit (list|cleantext)\s?(.*?):\)(.*?)\(:blogitend:\)/esi',
	"blogitMU_$1(PSS('$2'), PSS('$3'))");
Markup('blogit-skin', 'fulltext', '/\(:blogit-skin '.
	'(date|intro|author|tags|edit|commentcount|date|commentauthor|commentapprove|commentdelete|commentedit|commenttext|commentid)'.
	'\s?(.*?):\)(.*?)\(:blogit-skinend:\)/esi',
	"blogitSkinMU('$1', PSS('$2'), PSS('$3'))");
Markup('includesection', '>if', '/\(:includesection\s+(\S.*?):\)/ei',
	"PRR(bi_includeSection(\$pagename, PSS('$1 '.\$GLOBALS['bi_TemplateList'])))");
if (IsEnabled($EnableGUIButtons)){
	if ($action=='blogitedit' || ($action=='pmform' && $_REQUEST['target']==$bi_BlogForm) || $pagename == $bi_NewEntryPage)
		include_once($bi_Paths['guiedit']);  #PmWiki only includes this automatically if action=edit.
}else Markup('e_guibuttons', 'directives','/\(:e_guibuttons:\)/','');  #Prevent (:e_guibuttons:) markup appearing if guiedit not enabled.

# ----------------------------------------
# - Conditions
$Conditions['bi_ispage'] = 'bi_IsPage($condparm)';
$Conditions['bi_isdate'] = 'bi_IsDate($condparm)';
$Conditions['bi_isemail'] = 'bi_IsEmail($condparm)';
$Conditions['bi_auth'] = 'bi_Auth($condparm)';
$Conditions['bi_isnull'] = 'bi_IsNull($condparm)==""';
$Conditions['bi_lt'] = 'bi_LT($condparm)';

# ----------------------------------------
# - Markup Expressions
# if [0] is null or {$... then returns [1]; if [0] != null then returns ([2] or [0] if [2] is null)
$MarkupExpr['bi_ifnull'] = '( bi_IsNull($args[0])!="" ?( bi_IsNull($args[2])=="" ?$args[0] :$args[2]) :$args[1])';
# Calls to bi_encode should NOT be quoted: {(bi_encode {*$Title})} NOT {(bi_encode '{*$Title}')}. $args will contain each 'word' as an array element
$MarkupExpr['bi_encode'] = 'htmlentities(bi_IsNull(implode(\' \', $args)), ENT_QUOTES)';
# bi_param "group" "group_val"   Returns: group="group_val" if group_val != ""; else returns ""   0:param name; 1:value
$MarkupExpr['bi_param'] = '( bi_IsNull($args[1])=="" ?"" :"$args[0]=\"$args[1]\"")';
$MarkupExpr['bi_base'] = 'bi_BasePage($args[0])';
$MarkupExpr['bi_url'] = 'bi_URL($args)';
$MarkupExpr['bi_default_url'] = '($args[0]=="' .$bi_NewEntryPage .'" ?"' .$bi_DefaultGroup .'." :$args[0])';

# ----------------------------------------
# - Set GroupHeaderFmt and Footer
if (@$bi_EntryType == 'blog'){
	$bi_EntryStatus = PageTextVar($pagename,'entrystatus');
	if ( (($action=='blogitedit' || ($action=='pmform' && $_REQUEST['target']==$bi_BlogForm)) && bi_Auth('blog-edit')) )
		$GroupHeaderFmt .= '(:includesection "#blog-edit":)';  #Include GroupHeader on blog entry errors, as &action= is overriden by PmForms action.
	else{
		$bi_AuthEditAdmin = bi_Auth('blog-edit,blog-new,blogit-admin');
		if ( ($bi_EntryStatus!='draft' && (!bi_FuturePost($Now) || $bi_AuthEditAdmin) )
		|| ($bi_EntryStatus=='draft' && $bi_AuthEditAdmin) )
			$GroupHeaderFmt .= '(:includesection "#single-entry-view":)';  #Required for action=browse AND comments when redirected on error (in which case $action=pmform).
	}
} elseif  ($Group == $CategoryGroup)  $GroupHeaderFmt .= '(:title '.$AsSpacedFunction($Name).':)';
if ($Group == $CategoryGroup)  $GroupFooterFmt .= $bi_GroupFooterFmt;
if ($action=='print'){
	$GroupPrintHeaderFmt .= $GroupHeaderFmt;
	$GroupPrintFooterFmt .= $GroupFooterFmt;  #Needed if trying to print tag list.
	bi_AddMarkup();
}

# ----------------------------------------
# - HandleActions Functions
# ----------------------------------------
# If PmForms fails validation, and redirects to a browse, we need to define markup, since it isn't done as part of PmForm handling
# in Main Processing, as markup (tags) isn't processed if markup is defined.
function bi_HandleBrowse($pagename, $auth = 'read'){
global $_REQUEST,$bi_ResetPmFormField,$FmtPV,$HandleActions,$bi_OldHandleActions,$Group,$bi_CommentGroup;
	if ($Group == $bi_CommentGroup){  #After editing/deleting a comment page
		bi_Redirect(); return;
	} elseif (isset($bi_ResetPmFormField))
		foreach ($bi_ResetPmFormField  as $k => $v) {
			$_REQUEST["$k"]=$v;  #Reset form variables that have errors captured outside the PmForms mechanism
			$FmtPV['$bi_Default_'.$k]='"'.$v.'"';  #Always set, but used where values are stored in formats that don't handle errors (like Unix timestamps).
		}
	bi_AddMarkup();
	$HandleActions['browse']=$bi_OldHandleActions['browse'];
	HandleDispatch($pagename, 'browse');
}
function bi_HandleUnapproveComment($src, $auth='comment-approve'){
	bi_HandleApproveComment($src,$auth,false);
}
function bi_HandleApproveComment($src, $auth='comment-approve', $approve=true){
global $_REQUEST,$_POST,$Now,$ChangeSummary;
	if (bi_Auth($auth)){
		if ($src) $old = RetrieveAuthPage($src,'read',0, READPAGE_CURRENT);
		if($old){
			$new = $old;
			$new['csum'] = $new['csum:' .$Now] = $ChangeSummary = ($approve?'A':'Una').'pproved comment';
			$_POST['diffclass']='minor';
			$new['text'] = preg_replace(
				'/\(:commentapproved:'.($approve?'false':'true').':\)/', '(:commentapproved:'.($approve?'true':'false').':)',
				$new['text']);
			PostPage($src,$old,$new);  #Don't need UpdatePage, as we don't require edit functions to run
		}
	}
	bi_Redirect();
}
# Allow URL access to sections within $bi_TemplateList, including passed parameters.
function bi_HandleAdmin($src, $auth='blogit-admin'){
global $_GET,$GroupHeaderFmt;
	if (bi_Auth($auth)){
		if (isset($_GET['s'])){
			$args = bi_Implode($_GET, ' ', '=', array('n'=>'','action'=>'','s'=>''));
			$GroupHeaderFmt .= '(:title $[' .$_GET['s'] .']:)(:includesection "#' .$_GET['s'] ." $args \":)";
		}
	}
	HandleDispatch($src, 'browse');
}
function bi_HandleProcessForm ($src, $auth='read'){
global $bi_ResetPmFormField,$_POST,$RecipeInfo,$bi_BlogForm,$bi_EnablePostDirectives,$PmFormPostPatterns,$ROSPatterns,$CategoryGroup,
	$pagename,$bi_DefaultGroup,$bi_TagSeparator,$bi_CommentsEnabled,$bi_CommentForm,$Now,$bi_OldHandleActions,
	$EnablePost,$AutoCreate,$bi_DefaultCommentStatus,$bi_FixPageTitlePatterns;

	$bi_ResetPmFormField = array();
	if (@$_POST['target']==$bi_BlogForm && @$_POST['save']){
		#Allow future posts to create tag -- otherwise may never happen, since user may never edit the post again.
		if ( $_POST['ptv_entrystatus']!='draft' )  $AutoCreate['/^' .$CategoryGroup .'\./'] = array('ctime' => $Now);
		if ($bi_EnablePostDirectives)  $PmFormPostPatterns = array();  # Null out the PostPatterns so that directive markup doesn't get replaced.

		# Change field delimiters from (:...:...:) to section-tags [[#blogit_XXX]] for tags and body
		$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/s'] = '[[#blogit_entrybody]]$1[[#blogit_entrybodyend]]';  #entrybody MUST be the last variable.
		$ROSPatterns['/\(:pmmarkup:(.*?)(\(:title .*?:\)):\)/s'] = '[[#blogit_pmmarkup]]$1$2[[#blogit_pmmarkupend]]';  #This field contains (:TITLE:), so need to find .*?:)

		# url will be inherited from title, and will include a group from the url or the default group. If title is blank it is derived from url.
		if (!strpos($_POST['ptv_entryurl'], '.'))  $pg = $_POST['ptv_entryurl'];
		else  list($gr, $pg) = explode('.',$_POST['ptv_entryurl']);
		$title = preg_replace( array_keys( $bi_FixPageTitlePatterns ), array_values( $bi_FixPageTitlePatterns ), $_POST['ptv_entrytitle'] );

		# If valid date, then convert from user entered format to Unix format; otherwise force an error to be triggered in PmForms
		# NB: If page subsequently fails to post (due to incorrect p/w or captcha) then entrydate is already in unix time format.
		if (bi_IsDate($_POST['ptv_entrydate'])){ if (!preg_match('!\d{5,}!',$_POST['ptv_entrydate']))  $_POST['ptv_entrydate'] = strtotime($_POST['ptv_entrydate']); }
		else  $bi_ResetPmFormField['ptv_entrydate'] =  $_POST['ptv_entrydate'];  #if set, this is used in data-form to override unix timestamp value

		# Determine page name from title, replacing ' ' with '-' for seo.
		bi_setMakePageNamePatterns();
		$_POST['ptv_entrytype'] = 'blog';  #Prevent spoofing.
		$_POST['ptv_entrytitle'] = (empty($title) ?$pg :$_POST['ptv_entrytitle']);  #use either the url or the original title (not the clean title)
		$_POST['ptv_entryurl'] = MakePageName($pagename, ( empty($gr) ?$bi_DefaultGroup :$gr ) .'.' .( empty($pg) ?$title :$pg) );
		$_POST['ptv_pmmarkup'] = bi_GetPmMarkup($_POST['ptv_entrybody'], $_POST['ptv_entrytags'], $_POST['ptv_entrytitle']);

	}elseif ($bi_CommentsEnabled=='true' && @$_POST['target']==$bi_CommentForm){
		$_POST['ptv_entrytype'] = 'comment';
		$_POST['ptv_website'] = (!empty($_POST['ptv_website']) && substr($_POST['ptv_website'],0,4)!='http' ?'http://'.$_POST['ptv_website'] :$_POST['ptv_website']);
		$_POST['ptv_commentapproved'] = $bi_DefaultCommentStatus;
		$_POST['ptv_commentdate'] = $Now;
	}
	$bi_OldHandleActions['pmform']($src, $auth);
}
function bi_HandleDeleteComment($src, $auth='comment-edit') {  #action=blogitcommentdelete
global $bi_CommentGroup,$WikiDir,$Group,$LastModFile;
	if ($Group == $bi_CommentGroup && bi_Auth($auth.' '.$src) && RetrieveAuthPage($src,'read',0, READPAGE_CURRENT)){
		$WikiDir->delete($src);
		if ($LastModFile) { touch($LastModFile); fixperms($LastModFile); }
	}
	bi_Redirect();
}

# ----------------------------------------
# - Markup Functions
# ----------------------------------------
function blogitMU_more($options, $text){
	return (strpos($text, '[[#' .XL('break') .']]') !== false ?preg_replace('/{\$FullName}/', $options, '%blogit-readmore%[[{$FullName}#$[break] | $[Read more...]]]') :'');
}
function blogitMU_intro($options, $text){
	list($found,$null) = explode('[[#' .XL('break') .']]', $text, 2);
	return $found;
}
function blogitMU_list($name, $text){
	list($var, $label) = split('/', $text,2);
	$i = count($GLOBALS[$var]);
	foreach ($GLOBALS[$var] as $k)
		$t .= '(:input '. ($i==1 ?'hidden' :'select') .' name=' .$name .' value="' .$k .'" label="' .XL($k) .'" id="' .$var .'":)';
	return ($i==1?'':$label).$t;
}
# options is the length of the string, or use $bi_CommentSideBarLen is empty
function blogitMU_cleantext($options, $text){
global $bi_CommentSideBarLen, $pagename, $bi_UnstyleFn;
# SteP fixes: allow for unstyling; honor $options when empty($m); break $text on a word boundary
	if($bi_UnstyleFn>'')	$text = $bi_UnstyleFn($pagename, $text);
	$l = (empty($options) ?$bi_CommentSideBarLen :$options);
	$m = strpos($text, "\n");
	$m = ( empty($m) ?$l :min($m, $l) );
	preg_match('/^.{0,' .$m .'}\b/', $text, $match);
	return trim($match[0]);
}

function blogitSkinMU($fn, $opt, $txt){
global $bi_AuthorGroup,$pagename,$bi_TagSeparator,$bi_CommentsEnabled,$bi_LinkToCommentSite,$bi_CommentPattern;
	$args = ParseArgs($opt);  #$args['p'], args[]['s']
	$dateFmt = array('long'=>'%B %d, %Y, at %I:%M %p', 'short'=>'%B %d, %Y', 'entry'=>'%d-%m-%Y %H:%M');
	switch ($fn) {
		case 'date': return ME_ftime(XL($dateFmt[$args['fmt']]), '@'.$txt);
		case 'intro': return '(:div999991 class="'.$args['class'].'":)' .blogitMU_intro('', $txt) .'%blogit-more%'. blogitMU_more($args['page'], $txt) ."%%\n(:div99991end:)";
		case 'author': return ($txt>''
			?$args['pre_text'] .(PageExists(MakePageName($pagename, "$bi_AuthorGroup/$txt")) ?"[[$bi_AuthorGroup/$txt]]" :$txt) .$args['post_text']
			:'');
		case 'edit': return (bi_Auth('blog-edit '.$args['page']) ?$args['pre_text'] .'[['.$args['page'].'?action=blogitedit | '.$txt.']]'.$args['post-text'] :'');
		case 'tags': return ($txt>'' ?$args['pre_text'].bi_SaveTags('', html_entity_decode($txt, ENT_QUOTES), $bi_TagSeparator).$args['post_text'] :'');
		case 'commentcount': return ($args['status']!='none' && $bi_CommentsEnabled
			?$args['pre_text'].'[['.$args['group'].'.'.$args['name'].'#commentblock | '.
				'(:includesection "#comments-count-pagelist entrygroup=\''.$args['group'].'\' entryname=\''.$args['name'].'\' commentstatus=true":)'.
				$txt.']]'.$args['post_text']
			:'');
		case 'commentauthor': return ($bi_LinkToCommentSite=='true' && $args['website']>'' ?'[['.$args['website'].' | '.$args['author'].']]' :$args['author']);
		case 'commentapprove': return (bi_Auth('comment-approve '.bi_BasePage($txt))
			?$args['pre_text'].'[['.$txt.'?action=blogit'.($args['status']=='true'?'un':'').'approve | $['.($args['status']=='true'?'un':'').'approve]]]'.$args['post_text']
			:'');
		case 'commentedit': return (bi_Auth('comment-edit '.bi_BasePage($txt)) ?$args['pre_text'].'[['.$txt.'?action=edit | $[edit]]]'.$args['post_text'] :'');
		case 'commentdelete': return (bi_Auth('comment-edit '.bi_BasePage($txt)) ?$args['pre_text'].'[['.$txt.'?action=blogitcommentdelete | $[delete]]]'.$args['post_text'] :'');
		case 'commenttext': return ( strtr($txt, array("\r\n" => '<br />', "\r" => '<br />', "\n" => '<br />', "\x0B" => '<br />')) );
		case 'commentid': {
			$x = preg_match($bi_CommentPattern, $txt, $m );
			return 'ID'.$m[3];  #1-group; 2-name; 3-commentid
		}
	}
}
function bi_includeSection($pagename, $inclspec){
	$args = ParseArgs($inclspec);  #$inclspec: "params"
	$anc = array_shift($args['']);  #$anc: parameters for include; $args: include-paths
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
	if (preg_match('!\d{5,}!',$d)) $d=strftime(XL('%d-%m-%Y %H:%M'),$d);  #Convert Unix timestamp to EntryFormat
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
function bi_LT($arg){
	$arg = ParseArgs($arg);
	return (@$arg[''][0]<@$arg[''][1]);
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
function bi_BlogItAuth($pn, $level, $authprompt=true, $since=0) {
global $pagename, $action, $bi_AuthFunction, $bi_CommentsEnabled, $bi_CommentGroup, $bi_EntryType;
	# Set level to read if a non-authenticated user is posting a comment.
	if ( (($level=='edit') || ($level=='publish'))
		&& $action=='pmform' && $bi_EntryType == 'blog'
		&& IsEnabled($bi_CommentsEnabled,1) && preg_match("/^" .$bi_CommentGroup ."\./", $pn) ){
		$level = 'read';
		$authprompt = false;
	}
	$page=$bi_AuthFunction($pn, $level, $authprompt, $since);
	return $page;
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
global $bi_PrevUrl, $pagename;
	$r = FmtPageName('$PageUrl', (!empty($src)||empty($bi_PrevUrl) ?bi_BasePage(($src>'' ?$src :$pagename)) :$bi_PrevUrl));
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
global $PageTextVarPatterns;
	Markup('[[#blogit_anchor]]', '<split', $PageTextVarPatterns['[[#anchor]]'], '');
}
# Combines categories in body [[!...]] with separated tag list in tag-field.
# Stores combined list in tag-field in PmWiki format [[!...]][[!...]].
function bi_SaveTags($body, $user_tags, $sep) {
global $pagename;
	# Read tags from body, strip [[!...]]
	if ($body)  $bodyTags = (preg_match_all('/\[\[\!(.*?)\]\]/e', $body, $match) ?$match[1] :array());  #array of tags contained in [[!...]] markup.

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
global $bi_OldAsSpaced_Function,$bi_EntryType,$Group,$CategoryGroup,$action;
	if ($Group==$CategoryGroup || isset($bi_EntryType) || $action='blogitupgrade')  return (strtr($bi_OldAsSpaced_Function($text),'-',' '));
	else  return ($bi_OldAsSpaced_Function($text));
}
function bi_FuturePost($now){
global $pagename,$bi_DisplayFuture;
	$bi_EntryDate = PageTextVar($pagename,'entrydate');
	return ($bi_EntryDate>$now || $bi_DisplayFuture=='true');
}
function bi_GetPmMarkup($body, $tags, $title){
global $bi_TagSeparator;
	return bi_SaveTags($body, $tags, $bi_TagSeparator) .'(:title ' .$title .':)';
}

# ----------------------------------------
# - General Helper Functions
# ----------------------------------------
function bi_setFmtPV($a){
	foreach ($a as $k)  $GLOBALS['FmtPV']['$'.$k]='$GLOBALS["'.$k.'"]';
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
