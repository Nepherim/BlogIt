<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 David Gilbert.
    This file is blogit_convert.php; you can redistribute it and/or modify it under the terms of the GNU General Public
    License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

    For installation and usages instructions refer to: http://pmwiki.com/Cookbooks/BlogIt
*/
if (!isset($RecipeInfo['BlogIt'])) include_once("$FarmD/cookbook/blogit/blogit.php");
SDV($HandleActions['blogitconvert'], 'bic_ConvertPage'); SDV($HandleAuth['blogitconvert'], 'admin');
SDV($HandleActions['blogitupgrade'], 'bic_Upgrade'); SDV($HandleAuth['blogitupgrade'], 'admin');

SDVA($SitemapSearchPatterns, array());
$SitemapSearchPatterns[] = '!\.(All)?Recent(Changes|Uploads|Pages)$!';

#default: default value, if no old_value present.
#format: final structure of field -- $1 replaced with repeated_format.
#repeat_format: format for each value.
#old_fields: name of old field base values (ie, non-formated); assumes to use old field of same name if 'old_fields'==null
$bic_CurrentFields = array( #['20090227']
	'bi_version'=> array('default'=> str_replace('-','',$GLOBALS['RecipeInfo']['BlogIt']['Version'])),
	'pmmarkup'=> array('format'=>'(::pmmarkup:$1::)', 'repeat_format'=>'[[!$1]]', 'old_fields'=>'entrytags'),
	'blogid'=> array(),
	'entrytype'=> array('default'=> $GLOBALS['bi_PageType']['blog']),
	'entrydate'=> array(),
	'entryauthor'=> array(), #hardcoded to get author name from page attribute.
	'entrytitle'=> array(),
	'entrystatus'=> array('default'=> $GLOBALS['bi_StatusType']['draft']),
	'entrycomments'=> array('default'=> $GLOBALS['bi_CommentType']['open']),
	'entrytags'=> array(), #[[!]] is automatically stripped.
	'entrybody'=> array('format'=>'(::entrybody:$1::)')
);


#$bic_OldFields['2009-01-10'] = array( #Alpha2
$bic_OldFields = array( #Alpha2
	'blogid'=> array(),
	'entrytype'=> array(),
	'entrydate'=> array(),
	'entrytitle'=> array('format'=>'\(:title (.*):\)'),
	'entrystatus'=> array(),
	'entrycomments'=> array(),
	'entrytags'=> array('format'=>'\[\[\!(\w+)\]\]'),
	'entrybody'=> array()
);

#pattern: Group.,Group.Page,Group.
#writetofile: only writes to file if this is true -- otherwise outputs results to browser.
#mode=upgrade|convert|revert
#blogid=string
function bic_Upgrade($src, $auth='admin') {
global $bic_OldFields, $bic_CurrentFields, $bi_BlogGroups, $SearchPatterns, $bi_PageType, $bi_TagSeparator, $RecipeInfo;
$version = str_replace('-','',$RecipeInfo['BlogIt']['Version']);

	#Parameters
	$mode = (isset($_GET['mode']) ? $_GET['mode'] :'upgrade');
	$write = ($_GET['writetofile']=='true');
	$blogid = (isset($GLOBALS['_GET']['blogid']) ? $GLOBALS['_GET']['blogid'] : $GLOBALS['bi_BlogList']['blog1']);

	$t = @ListPages('/^('. (isset($_GET['pattern']) ?str_replace(',','|',$_GET['pattern']) :$src) .')/');
	foreach ($t as $i => $pn) {
		$pagetext = '';
		$org = RetrieveAuthPage($pn,$auth,0, READPAGE_CURRENT);
		if (!$org) {echo ('No privs to read<br/>'); continue;}

		$entryType = PageTextVar($pn,'entrytype');
		if ( ($mode=='convert' && empty($entryType)) || ($mode=='upgrade' && $entryType == $bi_PageType['blog'])) {
			echo("<b>$pn</b><br/>");

			foreach ($bic_CurrentFields as $new_field_name=>$new_field_attr){
				$new_field_val[$new_field_name] = '';

				#is the new field based on an old_field or was the field defined in the prior version, with the same name?
				$old_field = (isset($bic_OldFields[$bic_CurrentFields[$new_field_name]['old_fields']])
							?$bic_CurrentFields[$new_field_name]['old_fields']
							:(isset($bic_OldFields[$new_field_name]) ?$new_field_name :''));

				# Determine field value
				if (!empty($old_field)){
					$new_field_val[$new_field_name] = PageTextVar($pn,$old_field);

					# Get basic separated list with no formatting
					if ($bic_OldFields[$old_field]['format'])
						$new_field_val[$new_field_name] = implode($bi_TagSeparator,
							(preg_match_all('/'.$bic_OldFields[$old_field]['format'].'/', $new_field_val[$new_field_name], $m) ? $m[1] : array())
						);
				}

				if ($mode=='convert' && $new_field_name=='entrybody') $new_field_val[$new_field_name]=$org['text'];

				if (empty($new_field_val[$new_field_name]))
					if (isset($bic_CurrentFields[$new_field_name]['default'])) $new_field_val[$new_field_name] = $bic_CurrentFields[$new_field_name]['default'];
					elseif ($version == '20090227')
						if ($new_field_name=='entryauthor') $new_field_val[$new_field_name] = $org['author'];
						elseif ($new_field_name=='entrydate') $new_field_val[$new_field_name] = $org['ctime'];

				if (isset($bic_CurrentFields[$new_field_name]['repeat_format']) && !empty($new_field_val[$new_field_name])){
					$new_field_val[$new_field_name] = '[[!'.implode(']]'.$bi_TagSeparator.'[[!', explode($bi_TagSeparator, $new_field_val[$new_field_name]) ).']]';
				}

				# Format the field
				if (isset($bic_CurrentFields[$new_field_name]['format']))
					$new_field_val[$new_field_name] =
						str_replace('$1',$new_field_val[$new_field_name],$bic_CurrentFields[$new_field_name]['format']);
				else
					$new_field_val[$new_field_name] = '(:'.$new_field_name.':'.$new_field_val[$new_field_name].':)';

				$pagetext .= $new_field_val[$new_field_name]."\n";
			}

		}elseif ($mode=='revert' && $entryType == $bi_PageType['blog']) {
			$pagetext = PageTextVar($pn,'entrybody');

		}else echo ('<b>Nothing to '.$mode .'</b><br/>');

		if ($write) {
			if (!empty($pagetext)){
				$new = $org;
				$new['csum'] = $new['csum:' .$GLOBALS['Now'] ] = $GLOBALS['ChangeSummary'] = 'BlogIt Format: '.$mode;
				$new['diffclass']='minor';
				$new['text'] = $pagetext;
				PostPage($pn,$org,$new);  #Don't need UpdatePage, as we don't require edit functions to run
				echo ('<b>BlogIt page attributes written</b><br/>');
			}else
				echo ('<b>Nothing to write</b>');
		}
		echo (str_replace("\n",'<br>', $pagetext.'<br/><br/>'));
	}
	exit;  #Prevent original page loading.
}
