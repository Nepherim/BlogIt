<?php if (!defined('PmWiki')) exit();
$RecipeInfo['Blogger']['Version'] = '2009-01-10';

# User settable
SDV($Blogger_DefaultGroup, 'Blog');	#Pre-populates the Pagename field; blogs can exist in *any* group, not simply the default defined here.
SDV($Blogger_CommentGroup, 'Comments');
SDV($Blogger_BlogGroups, 'Blog');			# OPTIONAL: Comma separated list of Blog groups. This is purely to speed up pagelists. Defining this list does not mean all pages in the group are 'blog-pages'.
SDV($Blogger_CategoryGroup, 'Category');
SDV($Blogger_AuthorGroup, $AuthorGroup); #Defaults to 'Profiles'
SDV($Blogger_ReadMore, '%readmore%[[{$FullName}#break | Read more...]]');
SDV($Blogger_DateEntryFormat, '%d-%m-%Y %H:%M');
SDV($Blogger_DateDisplayFormat, $TimeFmt);
SDV($Blogger_BodyBreak, '[[#break]]');
SDV($Blogger_Templates, $SiteGroup .'.Blogger-Templates');
SDV($Blogger_EnablePostDirectives, true); # set to true to allow posting of directives of form (: :)
SDV($Blogger_TagSeparator, ', ');
SDVA($Blogger_StatusType, array('draft'=>'draft', 'publish'=>'publish'));
SDVA($Blogger_CommentType, array('open'=>'open', 'readonly'=>'read only', 'none'=>'none'));
SDVA($Blogger_BlogList, array('blog1'=>'blog'));

# Usable on wiki pages
setFmtPV(array('Now','Blogger_AuthorGroup','Blogger_DefaultGroup','Blogger_CommentGroup','Blogger_CategoryGroup','Blogger_DateEntryFormat',
	'Blogger_DateDisplayFormat','Blogger_Templates','Blogger_BlogForm','Blogger_CommentForm','Blogger_Type_BLOG'));
FmtPVA ($Blogger_StatusType, '$Blogger_StatusType');
FmtPVA ($Blogger_CommentType, '$Blogger_CommentType');
FmtPVA ($Blogger_BlogList, '$Blogger_BlogList');

# Slow: Set to 1 to exclude listing any pages for which the browser does not currently have read authorization
$EnablePageListProtect = 0;
$SearchPatterns['default'][] = '!\\.(All)?Recent(Changes|Uploads|Comments)$!';
$SearchPatterns['default'][] = '!\\.Group(Print)?(Header|Footer|Attributes)$!';
$SearchPatterns['default'][] = '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki|' .$Blogger_CategoryGroup .')\\.!';
$SearchPatterns['default'][] = FmtPageName('!^$FullName$!', $pagename);

$blogger['debug']=true;
debugLog('--------------------');

# Internal
$Blogger_BlogForm = 'blogger-entry';
$Blogger_CommentForm = 'blogger-comments';
$Blogger_Type_BLOG = 'blog';
$Group = PageVar($pagename, '$Group');

addPageStore();
include_once(dirname(__FILE__) .'/cookbook/pmform.php');
include_once("$FarmD/scripts/guiedit.php");
# Prevent viewing source and diff, primarily for Comments, as this would reveal email.
$HandleAuth['source'] = $HandleAuth['diff'] = 'edit';

# Doesn't pick up categories defined as page variables.
$LinkCategoryFmt = "<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>";
$CategoryGroup = $Blogger_CategoryGroup;	# Need to explicity set this.
$AutoCreate['/^' .$Blogger_CategoryGroup .'\./'] = array('ctime' => $Now);
if ($Group == $Blogger_CategoryGroup)
	$GroupFooterFmt = '(:include ' .$Blogger_Templates .'#tag-pagelist:)(:nl:)';

$PmForm[$Blogger_BlogForm] = 'form=' .$Blogger_Templates .'#blog-form fmt=' .$Blogger_Templates .'#blog-post';
$PmForm[$Blogger_CommentForm] = 'saveto="' .$Blogger_CommentGroup .'/{$Group}-{$Name}-' .date('Ymd\THms') .'" form=' .$Blogger_Templates .'#comment-form fmt=' .$Blogger_Templates .'#comment-post';

# Need to save entrybody in an alternate format (::entrybody:...::), to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['(::var:...::)'] = '/(\(:: *(\w[-\w]*) *:(?!\))\s?)(.*?)(::\))/s';
Markup('textvar::', '<split', '/\(::\w[-\w]*:(?!\)).*?::\)/s', '');  #Prevent (::...:...::) markup from being displayed.

$entryType = PageVar($pagename,'$:entrytype');
#foreach ($_POST as $p=>$k) debugLog($p .'=' .$k, true);
debugLog('entryType: '.$entryType. '   action: '.$action. '    Target: '.$_POST['target']);
# Blog entry being posted from PmForm (new or existing)
if ($action=='pmform' && $_POST['target']==$Blogger_BlogForm) {
	# Change field delimiters from (:...:...:) to (::...:...::) for tags and body; entrybody MUST be the last variable.
	$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/si'] = '(::entrybody:$1::)';
	$ROSPatterns['/\(:pmtags:(.*?):\)/si'] = '(::pmtags:$1::)';

	# Null out the PostPatterns means that directive markup doesn't get replaced.
	if ($Blogger_EnablePostDirectives == true)
		$PmFormPostPatterns = array();

	saveTags();
	$_POST['ptv_entrydate'] = strtotime($_POST['ptv_displaydate']); #Store dates in Unix format
}
if ($entryType == $Blogger_Type_BLOG) {
	$GroupHeaderFmt = '(:include ' .$Blogger_Templates .'#single-entry-view:)';  #Required for action=browse AND comments when redirected on error.
	if ($action=='bloggeredit' || ($action=='pmform' && $_POST['target']==$Blogger_BlogForm)){
		$GroupHeaderFmt = '(:include ' .$Blogger_Templates .'#blog-edit:)';
	}elseif ($action=='pmform' && $_POST['target']==$Blogger_CommentForm){
		$DefaultPasswords['edit']='';  #Remove edit password to allow initial posting of comment.
	}
}

# ----------------------------------------
# (:blogger [intro,more] options:)text(:bloggerend:)
Markup('blogger', 'fulltext',	'/\(:blogger ([more,intro,select,multiline]+)\s?(.*?):\)(.*?)\(:bloggerend:\)/esi',
	"bloggerMarkupHandler(PSS('$1'), PSS('$2'), PSS('$3'))");
function bloggerMarkupHandler($action, $options, $text){
	if ($action == 'more') {
		return (strpos($text, $GLOBALS['Blogger_BodyBreak']) !== false ? preg_replace('/{\$FullName}/', $options, $GLOBALS['Blogger_ReadMore']) : '');
	} elseif ($action == 'intro') {
		list($found,$null) = explode($GLOBALS['Blogger_BodyBreak'], $text);
		return $found;
	} elseif ($action == 'select') {
		foreach ($GLOBALS[$text] as $k => $v)
			$t .= '(:input select ' .$options .' "' .$k .'" "' .$v .'":)';
		return $t;
	} elseif ($action == 'multiline') {
		return preg_replace('/\n/', '<br />', $text);
	}
}
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
$MarkupExpr['bloggerBlogGroups'] = (empty($GLOBALS['Blogger_BlogGroups']) ? '""' : '"group=\"' .$GLOBALS['Blogger_BlogGroups'] .'\""');
$MarkupExpr['bloggerBasePage'] = 'bloggerBasePage($args[0])';
function bloggerBasePage($pn){
	return preg_replace('/^' .$GLOBALS['Blogger_CommentGroup'] .'[\/\.](.*?)-(.*?)-\d{8}T\d{6}$/','${1}/${2}',$pn);
}
# Combines categories in body [[!...]] with separated tag list in tag-field.
# Stores combined separated list in tag-field, and a [[!...]] list in hidden field to be picked up by PmWiki for backlinks, etc.
function saveTags() {
	global $_POST,$Blogger_TagSeparator;
	# Read tags from body, strip [[!...]]
	if (preg_match_all('/\[\[!(.*?)\]\]/', $_POST['ptv_entrybody'], $match))
		$bodyTags = $match[1];

	# Make sure tag-field entries are in standard separated format, and place in array
	if ($_POST['ptv_entrytags'])
		$fieldTags = explode($Blogger_TagSeparator, preg_replace('/'.trim($Blogger_TagSeparator).'\s*/', $Blogger_TagSeparator, $_POST['ptv_entrytags']));

	# Concatenate the tag-field tags, with those in the body,
	$allTags = array_unique(array_merge((array)$fieldTags, (array)$bodyTags));
	sort($allTags);

	#  generate a new separated string.
	$_POST['ptv_entrytags'] = ltrim(implode($Blogger_TagSeparator, $allTags), $Blogger_TagSeparator);
	$_POST['ptv_pmtags'] = ($_POST['ptv_entrytags']
		? '[[!' .preg_replace("/$Blogger_TagSeparator/", ']]'.$Blogger_TagSeparator.'[[!', $_POST['ptv_entrytags']) .']]' : '');
}
SDV($HandleActions['bloggerapprove'], 'bloggerApproveComment');
SDV($HandleAuth['bloggerapprove'], 'admin');
function bloggerApproveComment($pn, $auth='admin') {
	$old = RetrieveAuthPage($pn,$auth,0, READPAGE_CURRENT);
	if(!$old) exit();
	$new = $old;
	$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'Approved comment';
	$_POST['diffclass']='minor';
	$new['text'] .= '(:commentapproved:true:)';
	PostPage($pn,$old,$new);	# Don't need UpdatePage, as we don't require edit functions to run
	Redirect(bloggerBasePage($pn));
}
## General Helper Funtions ##
function setFmtPV($a){
	foreach ($a as $k)
		$GLOBALS['FmtPV']['$'.$k]='$GLOBALS["'.$k.'"]';
}
# Creates $FmtPV variables for each element of $a, named $n_VALUE
function FmtPVA ($a, $n) {
	foreach ($a as $k=>$v)
		$GLOBALS['FmtPV'][$n .'_' .strtoupper($k)] = "'" .$v ."'";
}
function addPageStore ($n='wikilib.d') {
	$GLOBALS['PageStorePath'] = dirname(__FILE__) ."/" .$n ."/{\$FullName}";
	$where = count($GLOBALS['WikiLibDirs']);
	if ($where>1) $where--;
	array_splice($GLOBALS['WikiLibDirs'], $where, 0, array(new PageStore($GLOBALS['PageStorePath'])));
}
function debugLog ($msg, $out=false) {
	if ($out || (!$out && $GLOBALS['blogger']['debug']) ) {
		error_log(date('r'). ' [blogger]: '. $msg);
	}
}
