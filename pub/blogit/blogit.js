jQuery.noConflict();
jQuery(document).ready(function($){
	$("<div/>").attr({id:"dialog"}).appendTo("body");
	if ($('.wikimessage').length){ $('html,body').animate({scrollTop: $('.wikimessage').offset().top-175}, 1); }
	BlogIt.fn.showMsg({msg:$('#wikiedit.blogit-blog-form .wikimessage').html(), result:'error'});
	BlogIt.fn.showMsg({msg:$('#wikitext .blogit-comment-form .wikimessage').html(), result:'success'}); //default to success, since no way to tell if error.

	$('#blogit-cancel').click(function() {
		//Restore initial data to prevent validation errors from changed field. Assume we loaded with valid data.
		var $form = $("#wikitext .blogit-comment-form").parent('form');
		if ($form.length) $form[0].reset();
		$form = $("#wikiedit.blogit-blog-form form");
		if ($form.length) $form[0].reset();
		return true;
	});

	$("#wikitext .blogit-comment-form").parents('form').validity(function() {
		$("#comment-author").require();
		$("#comment-email").require().match("email");
		$("#comment-website").match("url");
	});
	$.validity.patterns.entryDate = BlogIt.fmt['entry-date'];
	$("#wikiedit.blogit-blog-form form").validity(function() {
		$("#entrydate").match("entryDate");
		$("#entrytitle,#entryurl").assert(($("#entryurl").val() || $("#entrytitle").val()), BlogIt.fn.xl('Either enter a Blog Title or a Pagename'));
	});

	$("a[href*=action\=blogitapprove],a[href*=action\=blogitunapprove]").click(function(e){
		e.preventDefault();
		BlogIt.fn.ajax({ success: function(data){ BlogIt.fn.commentStatus(e.target, data); }}, e);
	});
	$("a[href*=action\=blogitcommentdelete],a[href*=action\=bi_de]").click( function(e){ BlogIt.fn.deleteDialog(e); });
	$("a[href*=action\=bi_bip]").click( function(e){ BlogIt.fn.commentBlock(e); });
	$("#wikiedit.blogit-blog-form form :input:not(:submit)").bind("change", function(){
		window.onbeforeunload = function(){ return BlogIt.fn.xl('You have unsaved changes.'); }
	});
	$('#wikiedit.blogit-blog-form form :input:submit').click(function(){
		window.onbeforeunload = null;  //Don't trigger on submit buttons.
	});
});

BlogIt.fn = function($){
	//private declarations
	var _unapprove;
	function updateCount(e,m){ return e.replace(': '+m, ': '+(_unapprove ?(parseInt(m)+1) :(m-1))); }
	function getEnteredIP(e){ return e+'&bi_ip='+$("#blogit_ip").val(); }

	//public functions
	return {
		xl: function(t){ return (BlogIt.xl[t] || t); },
		ajax: function(ajax, e){
			ajax["dataType"] = ajax.dataType || "json";
			ajax["url"] = ( typeof ajax.url == "function" ?ajax.url(e.target.href) :(ajax.url || e.target.href) ) + '&bi_mode=ajax';
			ajax["context"] = ajax.context || e.target;
			$.ajax(ajax);
		},
		dialogClose: function(){ $("#dialog").dialog("close").empty(); },
		dialogShow: function(txt, yes, no, ajax, e){
			$("#dialog").html(txt).dialog({
				resizable: false,
				modal: true,
				autoOpen: false
			});

			var btn={};
			btn[BlogIt.fn.xl(no)] = BlogIt.fn.dialogClose;
			btn[BlogIt.fn.xl(yes)] = function(){
				BlogIt.fn.ajax(ajax, e);
				BlogIt.fn.dialogClose();
			};

			$("#dialog").dialog('option', 'buttons', btn).dialog("open");
		},
		deleteDialog: function(e){
			e.preventDefault();
			BlogIt.fn.dialogShow(BlogIt.fn.xl("Are you sure you want to delete?"),'Yes','No',
				{success:function(data){ BlogIt.fn.objectRemove(e.target, data); }},e);
		},
		objectRemove: function(o, data){
			$($(o).parents('"[id^=bi_ID]"')[0]).fadeOut(500, function(){ $(this).remove(); });
			BlogIt.fn.showMsg(data);
		},
		showMsg: function(data){
			if (data.msg)  $.showMessage({
				'thisMessage':[data.msg],
				'className': data.result,
				'opacity': 95,
				'displayNavigation':	false,
				'autoClose': true,
				'delayTime': 2000
			});
		},
		commentBlock: function(e){
			e.preventDefault();
			BlogIt.fn.ajax({
				success: function(data){
					if (data.ip){
						BlogIt.fn.dialogShow(
							BlogIt.fn.xl('Commenter IP: ')+data.ip+'<br/>'+BlogIt.fn.xl('Enter the IP to block:')+
							'<input id="blogit_ip" type="text" value="'+data.ip+'"/>','Submit','Cancel',
							{ url: function(e){ return getEnteredIP(e); },
								success: function(data){
									BlogIt.fn.showMsg(data);
								}
							}, e);
					}
				}
			},e);
		},
		commentStatus: function(o, data){
			var $o = $($(o).parents('"[id^=bi_ID]"')[0]);
			var bg = $o.css("backgroundColor");
			$o.children().css({backgroundColor:'#BBFFB6'}).delay(500).fadeTo(500, 0.2, function () {
				$(this).fadeTo(500,1).css("background-color", bg);
			});
			_unapprove = ( $(o).html()==BlogIt.fn.xl("unapprove") );
			if (_unapprove){
				o.href = o.href.replace("blogitunapprove", "blogitapprove");
				$(o).html(BlogIt.fn.xl("approve"));
			}else{
				o.href = o.href.replace("blogitapprove", "blogitunapprove");
				$(o).html(BlogIt.fn.xl("unapprove"));
			}
			var cc_Obj = $('a[href*=action=blogitadmin&s=unapproved-comments]');
			var cc_Txt = cc_Obj.html();
			cc_Obj.html( cc_Txt.replace(new RegExp(BlogIt.fn.xl('Unapproved Comments:')+' (\\d*)'), updateCount) );
			BlogIt.fn.showMsg(data);
		}
	}
}(jQuery);

