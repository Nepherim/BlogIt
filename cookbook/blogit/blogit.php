<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usage instructions refer to: http://pmwiki.com/wiki/Cookbook/BlogIt
*/
$RecipeInfo['BlogIt']['Version'] = '2010-14-3';
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
SDV($bi_DefaultCommentStatus, (IsEnabled($EnablePostCaptchaRequired) ?'true' :'false') );  #auto-approve comments only if captcha is enabled
SDV($bi_LinkToCommentSite, 'true');
SDV($bi_EntriesPerPage, 15);
SDV($bi_DisplayFuture, 'false');
SDVA($bi_BlogList, array('blog1'));  #Ensure 'blog1' key remains; you can add keys for other blogs.
SDVA($bi_Auth, array('edit'=>array('comment-edit', 'comment-approve', 'blog-edit', 'blog-new', 'sidebar', 'blogit-admin')));  #key: role; value: array of actions

# ----------------------------------------
# - Advanced user settings
SDVA($bi_Pages, array('auth' => $bi_DefaultGroup .'.' .$DefaultName));  #edit/admin users need edit access to this page if not using AuthUser (page does not even need to exist)
SDV($bi_GroupFooterFmt, '(:includesection "#tag-pagelist":)(:nl:)');  #use to show all pages in a specific category when browsing a Tag group
SDV($bi_CommentSideBarLen, 60);
SDV($bi_TagSeparator, ', ');
SDV($bi_TitleSeparator, '-');
SDV($bi_EnablePostDirectives, true);  #Set to true to allow posting of directives of form (: :) in blog entries.
SDV($bi_StatAction, $TotalCounterAction);  #set by TotalCounter cookbook
SDV($bi_Cookie, $CookiePrefix.'blogit-');
SDV($bi_UnstyleFn, '');
SDV($bi_Skin, ($Skin>'' ?$Skin :'pmwiki'));  #Needed if skin is set in group config, which is processed after main config
SDV($bi_Ajax, '&bi_mode=ajax');
SDVA($bi_StatusType, array('draft', 'publish', 'sticky'));
SDVA($bi_CommentType, array('open', 'readonly', 'none'));
SDVA($bi_CommentApprovalType, array('true', 'false'));
SDV($PageNameChars,'-[:alnum:]' .($Charset=='UTF-8' ?'\\x80-\\xfe' :'') );
SDVA($bi_MakePageNamePatterns, array(
	"/'/" => '',  #strip single-quotes
	"/[^". $PageNameChars. "]+/" => $bi_TitleSeparator,  #convert everything else to hyphen
	"/(^\\" .$bi_TitleSeparator ."+)|(\\" .$bi_TitleSeparator ."+\$)/" => '',  #trim hyphens front and back
	"/\\" .$bi_TitleSeparator ."{2,}/" => $bi_TitleSeparator,  #trim duplicate hyphens
	($Charset=='UTF-8' ?"/^([\\xc0-\\xdf].)/e" :'//') => ($Charset=='UTF-8' ?"utf8toupper('$1')" :''),  #uppercase first letter
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
SDVA($bi_Pages, array('admin'=>$SiteGroup.'.BlogIt-Admin', 'blog_list'=>$SiteGroup.'.BlogList',	'blocklist'=>$SiteAdminGroup.'.Blocklist'));
SDV($bi_TemplateList, (isset($bi_Skin)?$SiteGroup.'.BlogIt-SkinTemplate-'.$bi_Skin.' ' : '') .$SiteGroup .'.BlogIt-CoreTemplate');
SDVA($bi_PageType, array('blog'));  #Comment is not in PageType list, since we don't want bloggers to be able to select 'comment' types.
SDV($FPLTemplatePageFmt, array(
	'{$FullName}', ($bi_Skin!='pmwiki' ?'{$SiteGroup}.BlogIt-SkinTemplate-'.$bi_Skin :''), '{$SiteGroup}.BlogIt-CoreTemplate',
	'{$SiteGroup}.LocalTemplates', '{$SiteGroup}.PageListTemplates'));
SDV($bi_CommentPattern, '/^' .$bi_CommentGroup .'[\/\.](.*?)-(.*?)-(\d{8}T\d{6}){1}\z/');
SDVA($bi_DateFmtRE,array('/%d|%e/'=>'(0?[1-9]|[12][0-9]|3[01])', '/%m/'=>'(0?[1-9]|1[012])', '/%g|%G|%y|%Y/'=>'(19\d\d|20\d\d)',
	'/%H|%I|%l/'=>'([0-1]?\d|2[0-3])', '/%M/'=>'([0-5]\d)'));
SDVA($SearchPatterns['blogit-comments'], array('comments' => $bi_CommentPattern));  #Used in pagelists
SDVA($SearchPatterns['blogit'], ($bi_BlogGroups>''  #either regexes to include ('/'), regexes to exclude ('!'):
	?array('blogit' => '/^(' .$bi_BlogGroups .')\./')
	:array(
		'recent' => '!\.(All)?Recent(Changes|Uploads|' .$bi_CommentGroup .')$!',
		'group' => '!\.Group(Print)?(Header|Footer|Attributes)$!',
		'pmwiki' => '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki)\.!',
		'self' => FmtPageName('!^$FullName$!', $pagename)
)));
SDV($PmFormRedirect,'bi_Redirect');
$bi_Forms=array('blogit-entry','blogit-comments');  //needs to be before cookies

# ----------------------------------------
# - Usable on Wiki Pages
bi_setFmtPV(array('bi_BlogIt_Enabled','bi_DefaultGroup','bi_CommentsEnabled','CategoryGroup','Now','bi_CommentGroup',
	'EnablePostCaptchaRequired','bi_DisplayFuture','bi_EntriesPerPage','bi_StatAction','action'));
bi_setFmtPVA(array('$bi_Pages'=>$bi_Pages));
$FmtPV['$bi_Mode']='$_REQUEST["bi_mode"]';

# ----------------------------------------
# - PmWiki Config
$HandleAuth['source'] = $HandleAuth['diff'] = 'edit';  #[1] Prevent viewing source and diff, primarily for Comments, as this would reveal email.
bi_addPageStore();
$bi_OriginalFn['AsSpacedFunction'] = $AsSpacedFunction;
$AsSpacedFunction = 'AsSpacedHyphens';  #[1]
# Doesn't pick up categories defined as page variables.
$LinkCategoryFmt = "<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>"; #[1]
$WikiStyleApply['row'] = 'tr';  #allows TR to be labelled with ID attributes

# ----------------------------------------
# - Authentication
SDV($AuthFunction,'PmWikiAuth');
$bi_OriginalFn['AuthFunction']=$AuthFunction;
$AuthFunction = 'bi_BlogItAuth';
# Cannot be done as part of handler due to scoping issues when include done in function
if ($action=='blogitupgrade' && bi_Auth('blogit-admin'))  include_once($bi_Paths['convert']);

# ----------------------------------------
# - Javascript
SDV($HTMLHeaderFmt['jquery-ui.css'], '<link rel="stylesheet" href="' .$PubDirUrl .'/blogit/jquery-ui/ui-lightness/jquery-ui.custom.css" type="text/css" />');
SDV($HTMLHeaderFmt['jquery-validity.css'], '<link rel="stylesheet" href="' .$PubDirUrl .'/blogit/jquery.validity.css" type="text/css" />');
SDV($HTMLHeaderFmt['blogit.css'], '<link rel="stylesheet" href="' .$PubDirUrl .'/blogit/blogit.css" type="text/css" />');
SDV($HTMLHeaderFmt['jquery.js'], '<script type="text/javascript" src="' .$PubDirUrl .'/blogit/jquery.js"></script>');
SDV($HTMLHeaderFmt['jquery-ui.js'], '<script type="text/javascript" src="' .$PubDirUrl .'/blogit/jquery-ui.custom.js"></script>');
SDV($HTMLHeaderFmt['jquery-validity.js'], '<script type="text/javascript" src="' .$PubDirUrl .'/blogit/jquery.validity.js"></script>');
SDV($HTMLHeaderFmt['jquery-showmessage.js'], '<script type="text/javascript" src="' .$PubDirUrl .'/blogit/jquery.showmessage.js"></script>');
$HTMLHeaderFmt['blogit.js']='<script type="text/javascript" src="' .$PubDirUrl .'/blogit/blogit.js"></script>';
$HTMLHeaderFmt['blogit-core']='<script type="text/javascript">
	BlogIt.pm["pubdirurl"]="'.$PubDirUrl.'/blogit";
	BlogIt.fmt["entry-date"]=/^'.bi_DateFmtRE().'$/;'."\n".
	bi_JXL()."\n".
'</script>';

# ----------------------------------------
# - Cookies
# Need to save entrybody in an alternate format to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['[[#anchor]]'] = '/(\[\[#blogit_(\w[_-\w]*)\]\](?: *\n)?)(.*?)(\[\[#blogit_\2end\]\])/s';
$pagename = ResolvePageName($pagename);  #undo clean urls (replace / with .) to make pagename checks easier
$bi_EntryType = PageTextVar($pagename,'entrytype');  #PageTextVar MUST be after PageTextVarPatterns declaration, otherwise on single-entry read, body is NULL.
bi_debugLog('entryType: '.$bi_EntryType);
list($Group, $Name) = explode('.', $pagename);
if ($pagename == $bi_Pages['blog_list'])	$FmtPV['$bi_BlogId']='"'.htmlentities(stripmagic($_GET['blogid'])).'"';
if ( bi_Auth('*') ){
	$EnablePostCaptchaRequired = 0;
	if (isset($bi_EntryType)||in_array($pagename,$bi_Pages)){
		# Cookies: Store the previous page (for returning on Cancel, comments approval, etc)
		$LogoutCookies[] = $bi_Cookie.'back-1'; $LogoutCookies[] = $bi_Cookie.'back-2';
		if (@$_POST['cancel'] && ($action=='pmform' && in_array($_REQUEST['target'],$bi_Forms)))  bi_Redirect();  //ajax cancel is handled client-side
		bi_storeCookie();
	}
}

# ----------------------------------------
# - PmForms Setup
include_once($bi_Paths['pmform']);
$PmFormPostPatterns['/\r/'] = '';  #fixes a bug in pmforms where multi-line entries/comments are stored across multiple lines in the base page
$PmFormTemplatesFmt = (isset($PmFormTemplatesFmt) ?$PmFormTemplatesFmt :array());
array_unshift ($PmFormTemplatesFmt,	($bi_Skin!='pmwiki' ?'{$SiteGroup}.BlogIt-SkinTemplate-'.$bi_Skin : ''), '{$SiteGroup}.BlogIt-CoreTemplate');
$bi_CommentPage=(preg_match($bi_CommentPattern,$pagename) ?$pagename :$bi_CommentGroup .'.' .$Group .'-' .$Name .'-' .date('Ymd\THms'));
SDV($PmForm['blogit-entry'], 'form=#blog-form-control fmt=#blog-post-control' .(@$_REQUEST['bi_mode']=='ajax' ?' successpage=""' :''));  //PmForm does a redirect browse if successpage is set
#if page is an existing comment (ie, has a comment page name) then use it, otherwise create it
SDV($PmForm['blogit-comments'],
	'saveto="' . $bi_CommentPage.'" '.
	'form=#comment-form-control fmt=#comment-post-control');

# ----------------------------------------
# - Handle Actions
$bi_OriginalFn['HandleActions'] = $HandleActions;
$HandleActions['pmform']='bi_HandleProcessForm';
$HandleActions['browse']='bi_HandleBrowse';
$HandleActions['print']='bi_HandleBrowse';
SDV($HandleActions['bi_admin'], 'bi_HandleAdmin'); SDV($HandleAuth['bi_admin'], 'blogit-admin');
SDV($HandleActions['bi_ca'], 'bi_HandleCommentApprove'); SDV($HandleAuth['bi_ca'], 'comment-approve');
SDV($HandleActions['bi_cua'], 'bi_HandleCommentUnapprove'); SDV($HandleAuth['bi_cua'], 'comment-approve');
SDV($HandleActions['bi_be'], 'bi_HandleEdit'); SDV($HandleAuth['bi_be'], 'blog-edit');
SDV($HandleActions['bi_ne'], 'bi_HandleEdit'); SDV($HandleAuth['bi_ne'], 'blog-new');
SDV($HandleActions['bi_ce'], 'bi_HandleEdit'); SDV($HandleAuth['bi_ce'], 'comment-edit');
SDV($HandleActions['bi_cr'], 'bi_HandleEdit'); SDV($HandleAuth['bi_cr'], 'comment-edit');  #comment-reply
SDV($HandleActions['bi_del'], 'bi_HandleDelete'); SDV($HandleAuth['bi_del'], 'comment-edit');
SDV($HandleActions['bi_bip'], 'bi_HandleBlockIP'); SDV($HandleAuth['bi_bip'], 'comment-approve');
SDV($HandleActions['bi_upgrade'], 'bi_HandleUpgrade'); SDV($HandleAuth['bi_upgrade'], 'admin');

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
	'(date|intro|author|tags|edit|newentry|delete|commentcount|date|commentauthor|commentapprove|commentdelete|commentedit|commentreply|commentblock|commenttext|commentid)'.
	'\s?(.*?):\)(.*?)\(:blogit-skinend:\)/esi',
	"blogitSkinMU('$1', PSS('$2'), PSS('$3'))");
Markup('includesection', '>if', '/\(:includesection\s+(\S.*?):\)/ei',
	"PRR(bi_includeSection(\$pagename, PSS('$1 '.\$GLOBALS['bi_TemplateList'])))");
$SaveAttrPatterns['/\\(:includesection\\s.*?:\\)/i'] = ' ';  #prevents include sections becoming part of page targets list
if (IsEnabled($EnableGUIButtons) && @$_REQUEST['bi_mode']!='ajax'){
	if ($action=='bi_be' || $action=='bi_ne' || ($action=='pmform' && $_REQUEST['target']=='blogit-entry'))
		include_once($bi_Paths['guiedit']);  #PmWiki only includes this automatically if action=edit.
}else Markup('e_guibuttons', 'directives','/\(:e_guibuttons:\)/','');  #Prevent (:e_guibuttons:) markup appearing if guiedit not enabled.

# ----------------------------------------
# - Conditions
$Conditions['bi_ispage'] = 'bi_IsPage($condparm)';
$Conditions['bi_isdate'] = 'bi_IsDate($condparm)';
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

# ----------------------------------------
# - HandleActions Functions
# ----------------------------------------
function bi_HandleBrowse($pagename, $auth = 'read'){
global $bi_ResetPmFormField,$bi_OriginalFn,$bi_GroupFooterFmt,$bi_EntryType,$bi_CommentGroup,$action,$_REQUEST,$Now,$Name,
	$HandleActions,$GroupPrintHeaderFmt,$GroupPrintFooterFmt,$GroupHeaderFmt,$GroupFooterFmt,$Group,$FmtPV,$CategoryGroup,$AsSpacedFunction;

bi_debugLog('HandleBrowse: '.$action.'['.$_REQUEST['bi_mode'].'] '.$_REQUEST['target']);
	if ($Group == $bi_CommentGroup){ bi_Redirect(); return; }  #After editing/deleting a comment page, and after HandlePmForm() has done a redirect()
	if ($_REQUEST['bi_mode']=='ajax'){ bi_AjaxRedirect(); return; }

	if ($action=='pmform' && $_REQUEST['target']=='blogit-entry'){
		if (isset($bi_ResetPmFormField))
			foreach ($bi_ResetPmFormField  as $k => $v) {
				$_REQUEST["$k"]=$v;  #Reset form variables that have errors captured outside the PmForms mechanism
				$FmtPV['$bi_Default_'.$k]='"'.$v.'"';  #Always set, but used where values are stored in formats that don't handle errors (like Unix timestamps).
			}
		$GroupHeaderFmt .= '(:includesection "#comment-edit":)';
	}elseif ($bi_EntryType == 'blog' && $action='browse'){
		$bi_EntryStatus = PageTextVar($pagename,'entrystatus');
		$bi_AuthEditAdmin = bi_Auth('blog-edit,blog-new,blogit-admin');
		if ( ($bi_EntryStatus!='draft' && (!bi_FuturePost($Now) || $bi_AuthEditAdmin) ) || ($bi_EntryStatus=='draft' && $bi_AuthEditAdmin) )
			$GroupHeaderFmt .= '(:includesection "#single-entry-view":)';  #Required for action=browse AND comments when redirected on error (in which case $action=pmform).
	}
	if ($Group == $CategoryGroup){
		if (@$bi_EntryType != 'blog')  $GroupHeaderFmt .= '(:title '.$AsSpacedFunction($Name).':)';
		$GroupFooterFmt .= $bi_GroupFooterFmt;
	}
	if ($action == 'print'){
		$GroupPrintHeaderFmt .= $GroupHeaderFmt;
		$GroupPrintFooterFmt .= $GroupFooterFmt;  #Needed if trying to print tag list.
	}
	bi_AddMarkup();  #If PmForms fails validation, and redirects to a browse, we need to define markup, since it isn't done as part of PmForm handling
	$bi_OriginalFn['HandleActions'][($action=='print' ?'print': 'browse')]($pagename, $auth);  #don't restore the original browse, since PmForm might do a handle browse redirect
}
# Return the comment form DOM if ajax request, or set the GroupHeader to the comment/blog form
function bi_HandleEdit($src, $auth='blog-edit'){  #action=(bi_be|bi_ne|bi_ce|bi_cr)
global $action,$_REQUEST,$pagename,$HandleActions,$bi_OriginalFn,$bi_EntryType,$GroupHeaderFmt,$bi_Pages;
bi_debugLog('HandleEdit');
	$type=($action=='bi_be'||($action=='bi_ne'&&$pagename==$bi_Pages['admin']) ?'blog' :'comment');
	if ( ($bi_EntryType==$type || $action=='bi_ne' || $action=='bi_cr') && bi_Auth($auth) ){
		if ($_REQUEST['bi_mode']=='ajax')  bi_AjaxRedirect(array('out'=>MarkupToHTML($pagename, '(:includesection "#' .$type .'-edit":)'), 'result'=>'success'));
		else  $GroupHeaderFmt .= '(:includesection "#' .$type .'-edit":)';
	}
	if ($type=='blog')  bi_AddMarkup();  #define markup to prevent PTV field being displayed at end of page
	$HandleActions['browse']=$bi_OriginalFn['HandleActions']['browse'];  //no need to goto blogit browse from bi_be
	HandleDispatch($src, 'browse');
}
function bi_HandleCommentUnapprove($src, $auth='comment-approve'){  #action=bi_cua
	bi_HandleCommentApprove($src,$auth,false);
}
function bi_HandleCommentApprove($src, $auth='comment-approve', $approve=true){  #action=bi_ca
global $_POST,$Now,$ChangeSummary,$_GET;
	$m = XL(($approve?'a':'una').'pprove comment');
	$result = array('msg'=>XL('Unable to ').$m, 'result'=>'error');
	if (bi_Auth($auth)){
		if ($src)  $old = RetrieveAuthPage($src,'read',false);
		if ($old){
			$new = $old;
			$new['csum'] = $new['csum:' .$Now] = $ChangeSummary = $m;
			$_POST['diffclass']='minor';
			$new['text'] = preg_replace(
				'/\(:commentapproved:'.($approve?'false':'true').':\)/', '(:commentapproved:'.($approve?'true':'false').':)',
				$new['text']);
			PostPage($src,$old,$new);  #Don't need UpdatePage, as we don't require edit functions to run
			$result = array('msg'=>ucfirst($m).' successful.', 'result'=>'success');
		}
	}
	bi_Redirect($_GET['bi_mode'], $result);
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
function bi_HandleProcessForm ($src, $auth='read'){  //$action=pmform
global $bi_ResetPmFormField,$_POST,$RecipeInfo,$bi_EnablePostDirectives,$ROSPatterns,$CategoryGroup,
	$pagename,$bi_DefaultGroup,$bi_TagSeparator,$bi_CommentsEnabled,$Now,$bi_OriginalFn,$GroupHeaderFmt,
	$AutoCreate,$bi_DefaultCommentStatus,$bi_FixPageTitlePatterns,$bi_CommentPattern,$Author,$EnablePostAuthorRequired;
bi_debugLog('HandleProcessForm: '.$_POST['bi_mode']);
	$bi_ResetPmFormField = array();

	if ( $_POST['target']=='blogit-entry' )
		$GroupHeaderFmt .= '(:includesection "#blog-edit":)';  #Include GroupHeader on blog entry errors, as &action= is overriden by PmForms action.
	if (@$_POST['target']=='blogit-entry' && (@$_POST['save']||@$_POST['bi_mode']=='ajax')){  //jquery doesn't serialize submit buttons
		#Allow future posts to create tag -- otherwise may never happen, since user may never edit the post again.
		if ( $_POST['ptv_entrystatus']!='draft' )  $AutoCreate['/^' .$CategoryGroup .'\./'] = array('ctime' => $Now);

		# Null out the PostPatterns so that directive markup doesn't get replaced.
		if ($bi_EnablePostDirectives){ unset($GLOBALS['PmFormPostPatterns']['/\\(:/']); unset($GLOBALS['PmFormPostPatterns']['/:\\)/']); }

		# Change field delimiters from (:...:...:) to section-tags [[#blogit_XXX]] for tags and body
		$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/s'] = '[[#blogit_entrybody]]$1[[#blogit_entrybodyend]]';  #entrybody MUST be the last variable.
		$ROSPatterns['/\(:pmmarkup:(.*?)(\(:title .*?:\)):\)/s'] = '[[#blogit_pmmarkup]]$1$2[[#blogit_pmmarkupend]]';  #This field contains (:TITLE:), so need to find .*?:)

		# url will be inherited from title, and will include a group from the url or the default group. If title is blank it is derived from url.
		if (!strpos($_POST['ptv_entryurl'], '.'))  $pg = $_POST['ptv_entryurl'];
		else  list($gr, $pg) = explode('.',$_POST['ptv_entryurl']);
		$title = preg_replace( array_keys( $bi_FixPageTitlePatterns ), array_values( $bi_FixPageTitlePatterns ), $_POST['ptv_entrytitle'] );

		# If valid date, then convert from user entered format to Unix format; otherwise force an error to be triggered in PmForms
		# NB: If page subsequently fails to post (due to incorrect p/w or captcha) then entrydate is already in unix time format.
		$_POST['ptv_entrydate'] = (empty($_POST['ptv_entrydate']) ?$Now :$_POST['ptv_entrydate']);
		if (bi_IsDate($_POST['ptv_entrydate'])){ if (!preg_match('!\d{5,}!',$_POST['ptv_entrydate']))  $_POST['ptv_entrydate'] = strtotime($_POST['ptv_entrydate']); }
		else  $bi_ResetPmFormField['ptv_entrydate'] =  $_POST['ptv_entrydate'];  #if set, this is used in data-form to override unix timestamp value

		# Determine page name from title, replacing ' ' with '-' for seo.
		bi_setMakePageNamePatterns();
		$_POST['ptv_entrytype'] = 'blog';  #Prevent spoofing.
		$_POST['ptv_entrytitle'] = (empty($title) ?$pg :$_POST['ptv_entrytitle']);  #use either the url or the original title (not the clean title)
		$_POST['ptv_entryurl'] = MakePageName($pagename, ( empty($gr) ?$bi_DefaultGroup :$gr ) .'.' .(empty($pg) ?$title :$pg) );
		$_POST['ptv_pmmarkup'] = bi_GetPmMarkup($_POST['ptv_entrybody'], $_POST['ptv_entrytags'], $_POST['ptv_entrytitle']);
		if (IsEnabled($EnablePostAuthorRequired,0))  $Author=$_POST['ptv_entryauthor'];

	#only set defaults if we're not editing the comment
	}elseif ($bi_CommentsEnabled=='true' && @$_POST['target']=='blogit-comments'){
		$_POST['ptv_entrytype'] = 'comment';
		$_POST['ptv_commenttext'] = rtrim($_POST['ptv_commenttext'],"\n\r\x0B")."\n";  #ensures markup is closed correctly (eg, links at end of comment)
		$_POST['ptv_website'] = (!empty($_POST['ptv_website']) && substr($_POST['ptv_website'],0,4)!='http' ?'http://'.$_POST['ptv_website'] :$_POST['ptv_website']);
		$ce=preg_match($bi_CommentPattern,$pagename) && bi_Auth('comment-edit');  #editing an existing comment?
		$_POST['ptv_commentapproved'] = ($ce ?$_POST['ptv_commentapproved'] :(bi_Auth('comment-approve,blogit-admin '.$pagename) ?'true' :$bi_DefaultCommentStatus));
		$_POST['ptv_commentdate'] = ($ce ?$_POST['ptv_commentdate'] :$Now);
		if (IsEnabled($EnablePostAuthorRequired,0))  $Author=$_POST['ptv_commentauthor'];
	}
bi_debugLog('Calling HandlePmForm');
	$bi_OriginalFn['HandleActions']['pmform']($src, $auth);  #usually HandlePmForm()
}
function bi_HandleDelete($src, $auth='comment-edit'){  #action=bi_del
global $bi_EntryType,$WikiDir,$LastModFile,$_GET;
	$result = array('msg'=>XL('Unable to perform delete operation.'), 'result'=>'error');
	if ( ($bi_EntryType=='comment' || $bi_EntryType=='blog')
		&& (bi_Auth( ($bi_EntryType=='comment' ?'comment-edit' :'blog-edit') .' ' .$src) && RetrieveAuthPage($src,'read',false, READPAGE_CURRENT)) ){
		$WikiDir->delete($src);
		if ($LastModFile) { touch($LastModFile); fixperms($LastModFile); }
		$result = array('msg'=>XL('Delete successful.'), 'result'=>'success');
	}
	bi_Redirect($_GET['bi_mode'], $result);
}
function bi_HandleBlockIP($src, $auth='comment-approve'){  #action=bi_bip
global $bi_EntryType,$_GET,$bi_Pages;
	$result = array('msg'=>XL('Unable to block IP address.'), 'result'=>'error');
	if ($bi_EntryType=='comment' && bi_Auth($auth.' '.$src)){
		if ($_GET['bi_ip']>''){
			Lock(2);
			$old = RetrieveAuthPage($bi_Pages['blocklist'], 'edit', false);
			if ($old){
				if (!preg_match('/\nblock:' .preg_replace(array('/\./','/\*/'),array('\\.','\\*'),$_GET['bi_ip']) .'\n/', $old['text'])) {
					$new = $old;
					if (substr($new['text'],-1,1) != "\n") $new['text'] .= "\n";
					$new['text'] .= 'block:'.$_GET['bi_ip'] ."\n";
					PostPage($bi_Pages['blocklist'],$old,$new);
					$result = array('msg'=>XL('Blocked IP address: ').$_GET['bi_ip'], 'result'=>'success', 'ip'=>$_GET['bi_ip']);
				}else  $result = array('result'=>'error', 'msg'=>XL('IP address is already being blocked: '.$_GET['bi_ip']));
			}else  $result = array('result'=>'error', 'msg'=>'Cannot edit '.$bi_Pages['blocklist']);

		}else{  #No IP passed in, so determine who created page
			$page = RetrieveAuthPage($src,'read',false);
			if ($page)
				foreach($page as $k=>$v)  #find the last diff in the list, which is the create point
					if (preg_match("/^diff:(\d+):(\d+):?([^:]*)/",$k,$match))  $ip = @$page['host:' .$match[1]];
			$result = array('result'=>($ip>'' ?'success' :'error'), 'ip'=>$ip, 'msg'=>($ip>'' ?'' :XL('Unable to determine IP address.')));
		}
	}
	bi_Redirect($_GET['bi_mode'], $result);
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
	list($var, $label) = explode('/', $text,2);
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
global $bi_AuthorGroup,$pagename,$bi_TagSeparator,$bi_CommentsEnabled,$bi_LinkToCommentSite,$bi_CommentPattern,$EnableBlocklist,$bi_Ajax,$bi_Pages;
	$args = ParseArgs($opt);  #$args['p'], args[]['s']
	$dateFmt = array('long'=>'%B %d, %Y, at %I:%M %p', 'short'=>'%B %d, %Y', 'entry'=>'%d-%m-%Y (%H:%M)');
	switch ($fn) {
		case 'date': return ME_ftime(XL(array_key_exists($args['fmt'],$dateFmt) ?$dateFmt[$args['fmt']] :$args['fmt']), '@'.$txt);
		case 'intro': return '(:div999991 class="'.$args['class'].'":)' .blogitMU_intro('', $txt) .'%blogit-more%'. blogitMU_more($args['page'], $txt) ."%%\n(:div99991end:)";
		case 'author': return ($txt>''
			?$args['pre_text'] .(PageExists(MakePageName($pagename, "$bi_AuthorGroup/$txt")) ?"[[$bi_AuthorGroup/$txt]]" :$txt) .$args['post_text']
			:'');
		case 'edit': return (bi_Auth('blog-edit '.$args['page']) ?$args['pre_text'] .'[['.$args['page'].'?action=bi_be' .$bi_Ajax .' | '.$txt.']]'.$args['post-text'] :'');
		case 'newentry': return (bi_Auth('blog-new '.$bi_Pages['auth']) ?$args['pre_text'] .'[['.$bi_Pages['admin'].'?action=bi_ne' .$bi_Ajax .' | '.$txt.']]'.$args['post-text'] :'');
		case 'delete': return (bi_Auth('blog-edit '.$args['page']) ?$args['pre_text'] .'[['.$args['page'].'?action=bi_del' .$bi_Ajax .' | '.$txt.']]'.$args['post-text'] :'');
		case 'tags': return ($txt>'' ?$args['pre_text'].bi_SaveTags('', html_entity_decode($txt, ENT_QUOTES), $bi_TagSeparator).$args['post_text'] :'');
		case 'commentcount': return ($args['status']!='none' && $bi_CommentsEnabled
			?$args['pre_text'].'[['.$args['group'].'.'.$args['name'].'#blogit-comment-list | '.
				'(:includesection "#comments-count-pagelist entrygroup=\''.$args['group'].'\' entryname=\''.$args['name'].'\' commentstatus=true":)'.
				$txt.']]'.$args['post_text']
			:'');
		case 'commentauthor': return ($bi_LinkToCommentSite=='true' && $args['website']>'' ?'[['.$args['website'].' | '.$args['author'].']]' :$args['author']);
		case 'commentapprove': return (bi_Auth('comment-approve '.bi_BasePage($txt))
			?$args['pre_text'].'[['.$txt.'?action=bi_'.($args['status']=='true'?'cua':'ca') .$bi_Ajax .' | $['.($args['status']=='true'?'un':'').'approve]]]'.$args['post_text']
			:'');
		case 'commentedit': return (bi_Auth('comment-edit '.bi_BasePage($txt)) ?$args['pre_text'].'[['.$txt.'?action=bi_ce' .$bi_Ajax .' | $[edit]]]'.$args['post_text'] :'');
		case 'commentdelete': return (bi_Auth('comment-edit '.bi_BasePage($txt)) ?$args['pre_text'].'[['.$txt.'?action=bi_del' .$bi_Ajax .' | $[delete]]]'.$args['post_text'] :'');
		case 'commentreply': return (bi_Auth('comment-edit '.bi_BasePage($txt)) ?$args['pre_text'].'[['.bi_BasePage($txt).'?action=bi_cr' .$bi_Ajax .' | $[reply]]]'.$args['post_text'] :'');
		case 'commentblock': return (IsEnabled($EnableBlocklist) && bi_Auth('comment-approve '.bi_BasePage($txt)) ?$args['pre_text'].'[['.$txt.'?action=bi_bip' .$bi_Ajax .' | $[block]]]'.$args['post_text'] :'');
		case 'commenttext': return ( strtr($txt, array("\r\n" => '<br />', "\r" => '<br />', "\n" => '<br />', "\x0B" => '<br />')) );
		case 'commentid': {
			$x = preg_match($bi_CommentPattern, $txt, $m );
			return 'bi_ID' .($x ?$m[3] :$txt);  #1-group; 2-name; 3-commentid, OR FullName for blog-list
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
function bi_IsDate($d, $f='%d-%m-%Y %H:%M'){  #accepts a date, and a date format (not a regular expression)
	if (empty($d))  return true;  #false causes two date invalid messages.
	if (preg_match('!\d{5,}!',$d))  $d=strftime(XL($f),$d);  #Convert Unix timestamp to a std format (must not include regular expressions)
	if (preg_match('!^'.bi_DateFmtRE($f).'$!',$d,$x)  #does %d match the regular expression version of $f? if it does m/d/y are in $x
		&& (checkdate($x[2], $x[1], $x[3]) || checkdate($x[1], $x[2], $x[3]) || checkdate($x[3], $x[1], $x[2]))
	)  return true;
	return false;
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
global $pagename, $action, $bi_OriginalFn, $bi_CommentsEnabled, $bi_CommentGroup, $bi_EntryType;
	# Set level to read if a non-authenticated user is posting a comment.
	if ( (($level=='edit') || ($level=='publish'))
		&& $action=='pmform' && $bi_EntryType == 'blog'
		&& IsEnabled($bi_CommentsEnabled,1) && preg_match("/^" .$bi_CommentGroup ."\./", $pn) ){
		$level = 'read';
		$authprompt = false;
	}
	return $bi_OriginalFn['AuthFunction']($pn, $level, $authprompt, $since);
}
# Called as part of markup condition. Determine whether the current user is authorized for an action
function bi_Auth($condparm){  #condparm: comma separated list of actions, and optional space separated pagename -- "blog-new,blog-edit Blog.This-entry"
global $AuthList,$bi_Auth,$pagename,$EnableAuthUser,$bi_Pages,$bi_OriginalFn,$action;
	@list($bi_actions, $pn) = explode(' ', $condparm, 2);
	$bi_actions=explode(',', trim($bi_actions,'\'"'));
	if (!IsEnabled($EnableAuthUser))
		$pn = ( in_array('*',$bi_actions)	|| in_array('sidebar',$bi_actions)
			||($pagename==$bi_Pages['admin'] && in_array('blogit-admin',$bi_actions))
			||($pagename==$bi_Pages['admin'] && $action=='bi_ne' && in_array('blog-new',$bi_actions)) )
			?$bi_Pages['auth']
			:(isset($pn)) ?$pn :$pagename;
	foreach ($bi_actions as $a){
		foreach ($bi_Auth as $role => $action_list){
			if ( $a=='*' || in_array($a, $action_list) ){  #Is the action assigned to a role?
				if ( (IsEnabled($EnableAuthUser) && $AuthList['@'.$role] > 0)  #the user is assigned to this role
					|| (!IsEnabled($EnableAuthUser) && $bi_OriginalFn['AuthFunction']($pn, $role, false, READPAGE_CURRENT)) )  #the user has these role privs on this page
					return true;
	}}}
	return false;
}

# ----------------------------------------
# - Internal Functions
# ----------------------------------------
function bi_ClearCache(){
global $PCache,$pagename;
	if (is_array($PCache[$pagename])) {
		foreach(array_keys($PCache[$pagename]) as $key)
			if (substr($key,0,3)=='=p_' || $key=='=pagetextvars')
				unset($PCache[$pagename][$key]);
	}
}
function bi_SendAjax($markup, $msg){
global $pagename;
bi_debugLog('bi_SendAjax: '.$markup);
	bi_ClearCache();  #Otherwise we retrieve the old values.
	echo(json_encode(array(  #admin list uses a different format for listing comments
		'out'=>MarkupToHTML($pagename, $markup),
		'result'=>'success',
		'msg'=>XL($msg)
	)));
}
function bi_AjaxRedirect($result=''){
global $pagename,$_REQUEST,$bi_CommentPage,$EnablePost,$MessagesFmt,$action, $Name, $Group;
bi_debugLog('AjaxRedirect: '.$_REQUEST['bi_style']);
	if ($EnablePost && count($MessagesFmt)==0){  #set to 0 is pmform failed (invalid captcha, etc)
		if ($_REQUEST['target']=='blogit-comments'){
			bi_SendAjax('(:includesection "' .($_REQUEST['bi_style']=='blogit-commentblock-admin' ?'#unapproved-comments' :'#comments-pagelist')
				.' commentid=' .$bi_CommentPage.' entrycomments=readonly":)',
				($bi_CommentPage==$pagename ?'Successfully updated comment.' :'Successfully added new comment.')
			);
		}elseif ($_REQUEST['target']=='blogit-entry'){
			bi_SendAjax('(:includesection "'.
				($_REQUEST['bi_style']=='blogit-post-summary'
					?'#blog-summary-pagelist group=' .$Group .' name='.$Name  #main blog summary page
					:($_REQUEST['bi_style']=='blogit-blog-list-row'  #blog list from admin page
						?'#blog-grid group=' .$Group .' name='.$Name
						:'#single-entry-view')  #single entry blog view
				)
				.'":)', 'Successfully '. ($action=='bi_ne' ?'added' :'updated') .' blog entry.');
		}else  echo(json_encode($result));
	}else  echo(json_encode(array('result'=>'error','msg'=>FmtPageName(implode($MessagesFmt), $pagename)) ));
	exit;
}
# Direct back to the refering page or $src
function bi_Redirect($src='', $result=''){
global $pagename,$_REQUEST;
bi_debugLog('Redirect');
	if ($src=='ajax' || $_REQUEST['bi_mode']=='ajax')  { bi_AjaxRedirect($result); }  #don't redirect ajax requests, just send back json object
	$history=bi_GetHistory();
	#use $src if provided, or history is empty; use pagename if $src and history are empty; use history if no $src and history exists.
	$r = ($src>''||empty($history) ?FmtPageName('$PageUrl', bi_BasePage(($src>'' ?$src :$pagename))) :$history);
bi_debugLog('Redirecting: '.$r);
	bi_storeCookie($r);

	header("Location: $r");
	header("Content-type: text/html");
	echo "<html><head>
	<meta http-equiv='Refresh' Content='URL=$r' />
	<title>Redirect</title></head><body></body></html>";
	exit;
}
function bi_GetHistory(){
global $bi_Cookie,$_COOKIE,$action,$_REQUEST;
	return ( ($action=='pmform' && $_REQUEST['target']=='blogit-entry' ?@$_COOKIE[$bi_Cookie.'back-2'] :@$_COOKIE[$bi_Cookie.'back-1']) );
}
function bi_storeCookie($url=''){
global $bi_Cookie,$pagename,$_REQUEST,$_GET,$action;
	if (empty($url)){
		$bi_Params = bi_Implode($_GET);
		$url = FmtPageName('$PageUrl', $pagename ) .(!empty($bi_Params) ?'?'.$bi_Params :'');  #current
	}
	$history = bi_GetHistory();
 	if ( $url!=$history && $_REQUEST['bi_mode']!='ajax' && $action!='pmform'){  #don't record if reloading, ajax, or redirected from pmform
		if (!empty($history))  setcookie($bi_Cookie.'back-2', $history, 0, '/');
		setcookie($bi_Cookie.'back-1', $url, 0, '/');  #don't replace cookies if user is reloading the current page
	}
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
global $bi_OriginalFn,$bi_EntryType,$Group,$CategoryGroup,$action;
	if ($Group==$CategoryGroup || isset($bi_EntryType) || $action=='blogitupgrade')  return (strtr($bi_OriginalFn['AsSpacedFunction']($text),'-',' '));
	else  return ($bi_OriginalFn['AsSpacedFunction']($text));
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
function bi_DateFmtRE($f='%d-%m-%Y %H:%M'){
global $bi_DateFmtRE;
	return preg_replace(array_keys($bi_DateFmtRE), array_values($bi_DateFmtRE), XL($f));
}
function bi_JXL(){  #create javascript array holding all XL translations of text used client-side
	$a=array('Are you sure you want to delete?', 'Yes', 'No', 'approve', 'unapprove', 'Unapproved Comments:', 'Commenter IP: ',
			'Enter the IP to block:', 'Submit', 'Post', 'Cancel', 'Either enter a Blog Title or a Pagename', 'You have unsaved changes.','Website:');
	foreach ($a as $k)  $t .= ($k!=XL($k) ?'BlogIt.xl["' .$k .'"]="' .XL($k) ."\";\n" :'');

	$a=array('require'=>'This field is required.', 'date'=>'This field must be formatted as a date.',
		'email'=>'This field must be formatted as an email.', 'url'=>'This field must be formatted as a URL.');
	foreach ($a as $k=>$v)  $t1 .= ($v!=XL($v) ?$k .':"' .XL($v) ."\",\n" :'');
	return ($t1>'' ?$t .'jQuery.extend(jQuery.validity.messages, {' .substr($t1,0,-2).'});' :$t);
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
