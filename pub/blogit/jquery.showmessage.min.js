/* 
 * jQuery.showMessage.js 1.4 - jQuery plugin
 * Author: Andrew Alba
 * http://showMessage.dingobytes.com/
 *
 * Copyright (c) 2009-2010 Andrew Alba (http://dingobytes.com)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * Built for jQuery library
 * http://jquery.com
 * 
 * Date: Mon Mar 15 11:23:00 2010 -0500
 */
var t;function closeMessage(a){t=setTimeout(function(){jQuery("#showMessage",window.parent.document).fadeOut()},a)}
jQuery(function(){jQuery(window).keydown(function(a){if((a===null?event.keyCode:a.which)==27){jQuery("#showMessage",window.parent.document).fadeOut();typeof t!="undefined"&&clearTimeout(t)}});jQuery.showMessage=function(a){a=jQuery.extend({thisMessage:[""],className:"notification",position:"top",opacity:90,displayNavigation:true,autoClose:false,delayTime:5E3},a);jQuery("#showMessage",window.parent.document).length&&jQuery("#showMessage",window.parent.document).remove();var b=jQuery("<div></div>").css({display:"none",
position:"fixed","z-index":101,left:0,width:"100%",margin:0,filter:"Alpha(Opacity="+a.opacity+")",opacity:a.opacity/100}).attr("id","showMessage").addClass(a.className);a.position=="bottom"?jQuery(b).css("bottom",0):jQuery(b).css("top",0);if(a.displayNavigation){var c=jQuery("<span></span>").css({"float":"right","padding-right":"1em","font-weight":"bold","font-size":"small"}).html("Esc Key or "),d=jQuery("<a></a>").attr({href:"",title:"close"}).css("text-decoration","underline").click(function(){jQuery("#showMessage",
window.parent.document).fadeOut();clearTimeout(t);return false}).text("close");jQuery(c).append(d);jQuery(b).append(c)}else jQuery(window).click(function(){if(jQuery("#showMessage",window.parent.document).length){jQuery("#showMessage",window.parent.document).fadeOut();jQuery(window).unbind("click");typeof t!="undefined"&&clearTimeout(t)}});c=jQuery("<div></div>").css({width:"90%",margin:"1em auto",padding:"0.5em"});d=jQuery("<ul></ul>").css({"font-size":"large","font-weight":"bold","margin-left":0,
"padding-left":0});for(var e=0;e<a.thisMessage.length;e++){var f=jQuery("<li></li>").html(a.thisMessage[e]).css({"list-style-image":"none","list-style-position":"outside","list-style-type":"none"});jQuery(d).append(f)}jQuery(c).append(d);jQuery(b).append(c);jQuery("body",window.parent.document).append(b);jQuery(b).fadeIn();a.autoClose&&closeMessage(a.delayTime)}});
