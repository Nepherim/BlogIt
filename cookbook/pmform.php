<?php if (!defined('PmWiki')) exit();
/*  Copyright 2007 Patrick R. Michaud (pmichaud@pobox.com)
    This file is pmform.php; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

*/

$RecipeInfo['PmForm']['Version'] = '2007-06-12';

if ($VersionNum < 2001946)
  Abort("pmform requires pmwiki-2.2.0-beta46 or later (currently $Version)");

if (@$_REQUEST['pmform'])
  $MessagesFmt[] =
    "<div class='wikimessage'>$[Post successful]</div>";

SDV($PmFormRedirectFunction,'Redirect');

SDV($FmtPV['$CurrentTime'], "\$GLOBALS['CurrentTime']");

SDV($PmFormTemplatesFmt,
  array('{$SiteGroup}.LocalTemplates', '{$SiteGroup}.PmFormTemplates'));

SDV($PmFormPostPatterns, array(
  '/\\(:/' => '( :',
  '/:\\)/' => ': )',
  '/\\$:/' => '$ :'));

SDVA($InputTags['pmform'], array(
  ':fn' => 'InputActionForm',
  ':args' => array('target'),
  ':html' => "<form action='{\$PageUrl}' \$InputFormArgs><input type='hidden' name='n' value='{\$FullName}' /><input type='hidden' name='action' value='pmform' />",
  'method' => 'post'));

Markup('pmform', '<input',
  '/\\(:pmform *([-\\w]+)( .*?)?:\\)/e',
  "PmFormMarkup(\$pagename, '$1', PSS('$2'))");

Markup('ptv:', 'block',
  '/^(\\w[-\\w]+)\\s*:.*$/',
  "<:block,0><div class='property-$1'>$0</div>");

SDV($HandleActions['pmform'], 'HandlePmForm');
SDV($HandleAuth['pmform'], 'read');


function PmFormConfig($pagename, $target) {
  global $PmForm, $PmFormPageFmt;
  $target_args = @$PmForm[$target];
  if (!$target_args) {
    $page = ReadPage(FmtPageName($PmFormPageFmt, $pagename));
    $pat = preg_quote($target, '/');
    if (preg_match("/^\\s*$pat\\s*:(.*)/m", @$page['text'], $match))
      $target_args = $match[1];
  }
  $target_args = trim($target_args);
  if (!$target_args) return array();
  return ParseArgs(FmtPageName($target_args, $pagename));
}


function PmFormTemplateDirective($text, $pat, &$match) {
  $pat = "/((?<=\n) *)?\\(:template +$pat\\b(.*?):\\)(?(1) *\n)/";
  return preg_match_all($pat, $text, $match, PREG_SET_ORDER);
}


function PmFormTemplateDefaults($pagename, &$text, $args=NULL) {
  $opt = array();
  if (!PmFormTemplateDirective($text, "defaults?", $match)) return $opt;
  foreach($match as $m) {
    if ($args) $m[2] = FmtTemplateVars($m[2], $args);
    $opt = array_merge($opt, ParseArgs($m[2]));
    $args = array_merge($opt, (array)$args);
    $text = str_replace($m[0], '', $text);
  }
  return $opt;
}


function PmFormTemplateRequires($pagename, &$text, $args=NULL) {
  if (!$args) $args = array();
  $errors = array();
  if (!PmFormTemplateDirective($text, "requires?", $match)) return;
  foreach($match as $m) {
    $text = str_replace($m[0], '', $text);
    if ($args) $m[2] = FmtTemplateVars($m[2], $args);
    $opt = ParseArgs($m[2]); $opt[''] = (array)$opt[''];
    $name = isset($opt['name']) ? $opt['name'] : array_shift($opt['']);
    $match = '?*';
    if (isset($opt['match'])) $match = $opt['match'];
    else if ($opt['']) $match = array_shift($opt['']);
    list($inclp, $exclp) = GlobToPCRE($match);
    foreach(preg_split('/[\\s,]+/', $name, -1, PREG_SPLIT_NO_EMPTY) as $n) {
      $n = preg_replace('/^\\$:/', 'ptv_', $n);
      if ($match == '' && $args[$n] != ''
          || ($inclp && !preg_match("/$inclp/is", $args[$n]))
          || ($exclp && preg_match("/$exclp/is", $args[$n])))
        $errors[] = isset($opt['errmsg']) ? $opt['errmsg']
                    : "$[Invalid parameter] $n";
    }
    if (@$opt['if'] && !CondText($pagename, 'if '.$opt['if'], 'hello'))
      $errors[] = isset($opt['errmsg']) ? $opt['errmsg']
                  : "$[Required condition failed]";
  }
  return $errors;
}


function PmFormMarkup($pagename, $target, $args) {
  global $PmFormTemplatesFmt;
  $target_opt = PmFormConfig($pagename, $target);
  $markup_opt = ParseArgs($args);
  $markup_opt['target'] = $target;
  $opt = array_merge($target_opt, $markup_opt);
  if (@$opt['form'])
    $form = RetrieveAuthSection($pagename, $opt['form'], $PmFormTemplatesFmt);
  $form_opt = PmFormTemplateDefaults($pagename, $form);
  $opt = array_merge($form_opt, $target_opt, $markup_opt);
  $form = PVSE(FmtTemplateVars($form, $opt));
  return PRR($form);
}


function HandlePmForm($pagename, $auth = 'read') {
  global $PmFormPostPatterns, $PmFormTemplatesFmt, $MessagesFmt,$PmFormRedirectFunction;
  $post_opt = RequestArgs($_POST);
  $pat = array_keys($PmFormPostPatterns);
  $rep = array_values($PmFormPostPatterns);
  foreach($post_opt as $k => $v)
    $post_opt[$k] = preg_replace($pat, $rep, $v);
  $target = @$post_opt['target'];
  $target_opt = PmFormConfig($pagename, $target);
  if (!$target_opt)
    return HandleDispatch($pagename, 'browse', "$[Unknown target] $target");

  ##  Now, get the message template we will use
  $msgtmpl = RetrieveAuthSection($pagename, @$target_opt['fmt'],
                                 $PmFormTemplatesFmt);

  $opt = array_merge($post_opt, $target_opt);
  $template_opt = PmFormTemplateDefaults($pagename, $msgtmpl, $opt);
  $opt = array_merge($template_opt, $post_opt, $target_opt);
  $safe_opt = array_merge($template_opt, $target_opt);
  $errors = PmFormTemplateRequires($pagename, $msgtmpl, $opt);

  if (!$errors && @$safe_opt['saveto'])
    $errors = PmFormSave($pagename, $msgtmpl, $opt, $safe_opt);

  if (!$errors && @$safe_opt['mailto'])
    $errors = PmFormMail($pagename, $msgtmpl, $opt, $safe_opt);

  if ($errors) {
    foreach ((array)$errors as $errmsg) {
      $errmsg = htmlspecialchars($errmsg, ENT_NOQUOTES);
      $MessagesFmt[] = "<div class='wikimessage'>$errmsg</div>";
    }
    return HandleDispatch($pagename, 'browse');
  }
  # $GLOBALS['EnableRedirect'] = 0;
  if (@$opt['successpage']) Redirect(MakePageName($pagename, $opt['successpage']));
#  Redirect($pagename, '{$PageUrl}?pmform=success');
  $PmFormRedirectFunction($pagename, '{$PageUrl}?pmform=success');
}


function PmFormSave($pagename, $msgtmpl, $opt, $safe_opt) {
  global $IsPagePosted;
  Lock(2);
  $saveto = MakePageName($pagename, $safe_opt['saveto']);
  $target = @$opt['target'];
  $page = ReadPage($saveto);
  if (preg_match("/.*\\(:pmform +$target( .*?)?:\\).*\n?/", @$page['text'], $mark)) {
    $mark_opt = ParseArgs($mark[1]);
    $mark_opt['=mark'] = $mark[0];
    $opt = array_merge($opt, $mark_opt);
    $safe_opt = array_merge($safe_opt, $mark_opt);
  }

  if (!@$mark) {
    $page = RetrieveAuthPage($saveto, 'edit', true);
    if (!$page) return '$[Edit permission required]';
  }

  $new = $page;
  $text = @$new['text'];
  $errors = NULL;
  if (preg_match('/\\S/', $msgtmpl)) {
    $msgtext = FmtTemplateVars($msgtmpl, $opt, $saveto);
    $errors = PmFormUpdateText($saveto, $text, $msgtext, $opt, $safe_opt);
  }
  if (!$errors && @$opt['savevars'])
    $errors = PmFormUpdateVars($saveto, $text, $opt);
  if (!$errors) {
    $new['text'] = $text;
    UpdatePage($saveto, $page, $new);
    if (!$IsPagePosted) return '$[Unable to save page]';
  }
  return $errors;
}


function PmFormUpdateText($pagename, &$text, $msgtext, $opt, $safe_opt) {
  if (preg_match('/^\\s*([^\\s,]+)/', @$opt['where'], $w)) $where = $w[1];
  else $where = 'new';
  if (@$opt['where'] != @$safe_opt['where']) {
    list($inclp, $exclp) = GlobToPCRE(@$safe_opt['where']);
    if (!preg_match("/$inclp/", $where) || preg_match("/$exclp/", $where))
      return "$[Invalid 'where' option] 1";
  }
  if ($where == 'new') {
   if (!isset($text)) $text = $msgtext;
   return NULL;
  }
  $mark = @$safe_opt['=mark'];
  switch ($where) {
    case 'top'   :  $ipos = 0; $ilen = 0; break;
    case 'bottom':  $ipos = strlen($text); $ilen = 0; break;
    case 'above' :  $ipos = strpos($text, $mark); $ilen = 0; break;
    case 'below' :  $ipos = strpos($text, $mark) + strlen($mark); $ilen = 0; break;
    default:
      return "$[Invalid 'where' option] 2";
  }
  $text = substr_replace($text, $msgtext, $ipos, $ilen);
  return NULL;
}


function PmFormUpdateVars($pagename, &$text, $opt) {
  global $PageTextVarPatterns;
  if (!@$opt['savevars']) return NULL;
  foreach(preg_split('/[\\s,]+/', $opt['savevars']) as $v)
    @$savevars[preg_replace('/^\\$:/', '', $v)]++;
  foreach($PageTextVarPatterns as $pat) {
    if (!preg_match_all($pat, $text, $match, PREG_SET_ORDER)) continue;
    foreach($match as $m) {
      $var = $m[2]; if (!@$savevars[$var]) continue;
      $val = $opt["ptv_$var"];
      if (!preg_match('/s[eimu]*$/', $pat))
        $val = str_replace("\n", ' ', $val);
      $text = str_replace($m[0], $m[1] . $val . $m[4], $text);
      unset($savevars[$var]);
    }
  }
  foreach($savevars as $var => $v) {
    $val = $opt["ptv_$var"];
    $text .= "(:$var:$val:)\n";
  }
  return NULL;
}


function PmFormMail($pagename, $msgtmpl, $opt, $safe_opt) {
  global $PmFormMailHeaders, $PmFormMailParameters;
  SDV($PmFormMailHeaders, '');
  SDV($PmFormMailParameters, '');

  if (!preg_match('/\\S/', $msgtmpl)) $msgtmpl = '{$$text}';
  $msgtext = FmtTemplateVars($msgtmpl, $opt, $pagename);
  $mailto = preg_split('/\\s*,\\s*/', @$safe_opt['mailto'], -1, PREG_SPLIT_NO_EMPTY);
  $mailto = implode(', ', $mailto);
  $from = $opt['from'];
  $subject = $opt['subject'];
  $header = $PmFormMailHeaders;
  if ($from) $header = "From: $from\r\n$header";
  $header = preg_replace("/[\r\n]*$/", '', $header);

  if ($PmFormMailParameters)
    $tf = mail($mailto, $subject, $msgtext, $header, $MailFormParameters);
  else
    $tf = mail($mailto, $subject, $msgtext, $header);

  if (!$tf) return '$[An error has occurred]';
  return NULL;
}
