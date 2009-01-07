<?php if (!defined('PmWiki')) exit();
$RecipeInfo['Blogger']['Version'] = '2009-01-10';
$blogger['debug']=true;
debugLog('--------------------');
#foreach ($_POST as $p=>$k) debugLog($p .'=' .$k, true);

# Common user settable
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
SDV($Blogger_Templates, $SiteGroup .'.Blogger-Templates');
SDV($Blogger_EnablePostDirectives, true); #Set to true to allow posting of directives of form (: :) in blog entries.
SDV($Blogger_TagSeparator, ', ');
SDVA($Blogger_StatusType, array('draft'=>'draft', 'publish'=>'publish'));
SDVA($Blogger_CommentType, array('open'=>'open', 'readonly'=>'read only', 'none'=>'none'));
SDVA($Blogger_BlogList, array('blog1'=>'blog1'));  #Ensure 'blog1' key remains; you can rename the blog (2nd parameter). Also define other blogs.
SDVA($Blogger_PageType, array('blog'=>'blog'));  # INTERNAL USE ONLY

#$FPLTemplatePageFmt
# Usable on wiki pages
setFmtPV(array('Now','Blogger_AuthorGroup','Blogger_DefaultGroup','Blogger_CommentGroup','Blogger_CommentsEnabled','Blogger_CategoryGroup',
	'Blogger_DateEntryFormat','Blogger_DateDisplayFormat','Blogger_Templates','Blogger_BlogForm','Blogger_CommentForm'));
FmtPVA(array('$Blogger_StatusType'=>$Blogger_StatusType, '$Blogger_CommentType'=>$Blogger_CommentType,
	'$Blogger_BlogList'=>$Blogger_BlogList, '$Blogger_PageType'=>$Blogger_PageType));

# Internal
$Blogger_BlogForm = 'blogger-entry';
$Blogger_CommentForm = 'blogger-comments';
$Group = PageVar($pagename, '$Group');

addPageStore();
SDV($PageListCacheDir, $FarmD.'/work.d/');
SDV($EnablePageIndex, 1);
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

# Slow: Set to 1 to exclude listing any pages for which the browser does not currently have read authorization
$EnablePageListProtect = 0;
$SearchPatterns['default'][] = '!\\.(All)?Recent(Changes|Uploads|Comments)$!';
$SearchPatterns['default'][] = '!\\.Group(Print)?(Header|Footer|Attributes)$!';
$SearchPatterns['default'][] = '!^('. $SiteGroup .'|' .$SiteAdminGroup .'|PmWiki|' .$Blogger_CategoryGroup .')\\.!';
$SearchPatterns['default'][] = FmtPageName('!^$FullName$!', $pagename);

# Need to save entrybody in an alternate format (::entrybody:...::), to prevent (:...:) markup confusing the end of the variable definition.
$PageTextVarPatterns['(::var:...::)'] = '/(\(:: *(\w[-\w]*) *:(?!\))\s?)(.*?)(::\))/s';
$PmForm[$Blogger_BlogForm] = 'form=' .$Blogger_Templates .'#blog-form fmt=' .$Blogger_Templates .'#blog-post';
$PmForm[$Blogger_CommentForm] = 'saveto="' .$Blogger_CommentGroup .'/{$Group}-{$Name}-' .date('Ymd\THms') .'" form=' .$Blogger_Templates .'#comment-form fmt=' .$Blogger_Templates .'#comment-post';

$entryType = PageVar($pagename,'$:entrytype');
debugLog('entryType: '.$entryType. '   action: '.$action. '    Target: '.$_POST['target']);
# Blog entry being posted from PmForm (new or existing)
if ($action && $action=='pmform' && $_POST['target']==$Blogger_BlogForm) {
	if ($Blogger_EnablePostDirectives == true)
		$PmFormPostPatterns = array();  # Null out the PostPatterns means that directive markup doesn't get replaced.

	# Change field delimiters from (:...:...:) to (::...:...::) for tags and body; entrybody MUST be the last variable.
	$ROSPatterns['/\(:entrybody:(.*?)(:\))$$/si'] = '(::entrybody:$1::)';
	$ROSPatterns['/\(:pmtags:(.*?):\)/si'] = '(::pmtags:$1::)';
	$ROSPatterns['/\(:pmtitle:(.*?:\)):\)/si'] = '(::pmtitle:$1::)';	#This field contains (:TITLE:), so need to find .*?:)
	saveTags();
	$_POST['ptv_entrydate'] = strtotime($_POST['ptv_displaydate']); #Store dates in Unix format
	$_POST['ptv_pmtitle'] = '(:title ' .$_POST['ptv_entrytitle'] .':)';
	if ( $Blogger_DefaultGroup && (empty($_POST['ptv_entryurl']) || $_POST['ptv_entryurl']==$Blogger_DefaultGroup.'.') )
		$_POST['ptv_entryurl'] = $Blogger_DefaultGroup .'.' .$_POST['ptv_entrytitle'];
}else{
	# NOTE: Must not be declared if processing a pmform, as tags don't get generated.
	Markup('textvar::', '<split', '/\(::\w[-\w]*:(?!\)).*?::\)/s', '');  # Prevent (::...:...:) markup from being displayed.
}
if ($entryType && $entryType == trim($FmtPV['$Blogger_PageType_BLOG'],'\'')){
	$GroupHeaderFmt = '(:include ' .$Blogger_Templates .'#single-entry-view:)';  #Required for action=browse AND comments when redirected on error.
	if ($action=='bloggeredit' || ($action=='pmform' && $_POST['target']==$Blogger_BlogForm)){
		#Need to include GroupHeader on blog entry errors, when &action=edit is not passed back by PmForms.
		$GroupHeaderFmt = '(:include ' .$Blogger_Templates .'#blog-edit:)';
	}elseif ($Blogger_CommentEnabled=='true' && $action=='pmform' && $_POST['target']==$Blogger_CommentForm){
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
	}elseif ($action == 'intro') {
		list($found,$null) = explode($GLOBALS['Blogger_BodyBreak'], $text);
		return $found;
	}elseif ($action == 'select') {
		list($var, $label) = split('/', $text);
		$i = count($GLOBALS[$var]);
		foreach ($GLOBALS[$var] as $k => $v)
			$t .= '(:input '. ($i==1?'hidden':'select') .' ' .$options .' "' .$k .'" "' .$v .'":)';
		return ($i==1?'':$label) .$t;
	}elseif ($action == 'multiline')
		return preg_replace('/\n/', '<br />', $text);
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
# Parameters: 0:if/noif 1:variable 2:value 3:[&&,||]
$MarkupExpr['bloggerIfVar'] = '(!preg_match("/\\{.*?\\}/",$args[2])?(!empty($args[3])?$args[3]." ":"").($args[0]=="if"?"if=\\"":"")."equal {=\\$:$args[1]} $args[2]".($args[0]=="if"?"\\"":"") : "")';
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
## General Helper Funtions ##
function setFmtPV($a){
	foreach ($a as $k)
		$GLOBALS['FmtPV']['$'.$k]='$GLOBALS["'.$k.'"]';
}
# Creates $FmtPV variables for each element of $a, named $n_VALUE
function FmtPVA ($a){
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
