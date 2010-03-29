// blogit.js 2010-14-3
jQuery.noConflict();
jQuery(document).ready(function($){
	$("<div/>").attr({id:"dialog"}).appendTo("body");
	$('#dialog').dialog({ resizable: true, modal: true, autoOpen: false });  //set defaults

	if ($('.wikimessage').length){ $('html,body').animate({scrollTop: $('.wikimessage').offset().top-175}, 1); }
	BlogIt.fn.showMsg({msg:$('#wikiedit.blogit-blog-form .wikimessage').html(), result:'error'});
	BlogIt.fn.showMsg({msg:$('#wikitext .blogit-comment-form .wikimessage').html(), result:'success'}); //default to success, since no way to tell if error.

	$('#blogit-cancel').click(function() {  //Restore initial data to prevent validation errors from changed field. Assume we loaded with valid data.
		var $form = ($("#wikiedit").length ?$("#wikiedit.blogit-blog-form form") :$("#wikitext .blogit-comment-form").closest('form'));  //on the read page or edit page
		$form[0].reset();
		return true;
	});

	$("#wikitext .blogit-comment-form").closest('form').validity(function() {
		$("#comment-author").require();
		$("#comment-email").require().match("email");
		$("#comment-website").match("url");
	});
	$.validity.patterns.entryDate = BlogIt.fmt['entry-date'];
	$("#wikiedit.blogit-blog-form form").validity(function() {
		$("#entrydate").match("entryDate");
		$("#entrytitle,#entryurl").assert(($("#entryurl").val() || $("#entrytitle").val()), BlogIt.fn.xl('Either enter a Blog Title or a Pagename'));
	});

	$("a[href*=action\=blogitapprove],a[href*=action\=blogitunapprove]").live('click', function(e){
		e.preventDefault();
		BlogIt.fn.ajax({ success: function(data){ BlogIt.fn.commentStatus(e.target, data); }}, e);
	});
	$("a[href*=action\=blogitcommentdelete],a[href*=action\=bi_de]").live('click', function(e){ BlogIt.fn.deleteDialog(e); });
	$("a[href*=action\=bi_bip]").live('click', function(e){ BlogIt.fn.commentBlock(e); });
	$("a[href*=action\=bi_qc]").live('click', function(e){ BlogIt.fn.quickEditComment(e); });
	$("a[href*=action\=bi_rc]").live('click', function(e){ BlogIt.fn.replyComment(e); });
	$("#wikiedit.blogit-blog-form form :input:not(:submit)").live('change', function(){  //if any field (not a submit button) changes...
		window.onbeforeunload = function(){ return BlogIt.fn.xl('You have unsaved changes.'); }
	});
	$('#wikiedit.blogit-blog-form form :input:submit').live('click', function(){
		window.onbeforeunload = null;  //Don't trigger on submit buttons.
	});
});

var BlogIt={ fmt:{}, xl:{}, fn:{}, pm:{} };
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
		dialogShow: function(txt, yes, no, w, ajax, e){
			var $d = $("#dialog");
			$d.html(txt).dialog('option', 'width', w);
			var btn={};
			if (no) btn[BlogIt.fn.xl(no)] = BlogIt.fn.dialogClose;
			if (yes) btn[BlogIt.fn.xl(yes)] = function(){
				BlogIt.fn.ajax(ajax, e);
				BlogIt.fn.dialogClose();
			};
			if (yes||no) $d.dialog('option', 'buttons', btn);
			$d.dialog("open");
		},
		deleteDialog: function(e){
			e.preventDefault();
			BlogIt.fn.dialogShow(BlogIt.fn.xl("Are you sure you want to delete?"),'Yes','No','300px',
				{success:function(data){ BlogIt.fn.objectRemove(e.target, data); }},e);
		},
		objectRemove: function(o, data){
			$(o).closest('"[id^=bi_ID]"').fadeOut(500, function(){ $(this).remove(); });
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
							'<input id="blogit_ip" type="text" value="'+data.ip+'"/>','Submit','Cancel','300px',
							{	url: function(e){ return getEnteredIP(e); },
								success: function(data){ BlogIt.fn.showMsg(data); }
							}, e);
					}
				}
			},e);
		},
		commentStatus: function(o, data){
			var $o = $(o).closest('"[id^=bi_ID]"');
			BlogIt.fn.flash($o, data);
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
		},
		quickEditComment: function(e){
			e.preventDefault();
			$.ajax({dataType:'json', url:e.target.href,  //get the comment form from pmwiki
				success: function(data){
					if (data.out){  //form returned in data.out
						$("#dialog").html(data.out).dialog('option', 'width', '500px').dialog("open");  //load the comment form into a dialog
						$('#dialog form')
							.prepend('<input type="hidden" value="ajax" name="bi_mode">')  //trigger ajax mode
							.submit(function(){  //when users hits Post, send form data
								$.ajax({type: 'POST', dataType:'json', url:$(this).attr('action'),
									data: $(this).serialize(),
									success: function(data){  //after PmForms finishes processing, update page with new content
										$('#dialog').dialog("close");
										var id = '#'+$(e.target).closest('"[id^=bi_ID]"').attr('id');  //need to get ID as we remove old DOM element
										$(id).replaceWith($(data.out).filter(id));  //update page with new content
										BlogIt.fn.flash($(id), data);
									}
								});
								return false;  //ensure form doesn't do normal processing on Post
							});
					}
				}
			});
		},
		replyComment: function(e){
			e.preventDefault();
			$.ajax({dataType:'json', url:e.target.href, context:e,  //get the comment form from pmwiki
				success: function(data){
					if (data.out){  //load the comment form into a dialog, which does the normal comment edit process on submit
						$("#dialog").html(data.out).dialog('option', 'width', '500px').dialog("open");
//						$('#dialog form').prepend('<input type="hidden" value="ajax" name="bi_mode">');
					}
				}
			});
		},
		flash: function(o, data){
			var bg = o.css("background-color");
			o.css({backgroundColor:'#BBFFB6'});
			o.css({backgroundColor:'#BBFFB6'}).delay(500).fadeTo(500, 0.2, function () {
				$(this).fadeTo(500,1).css("background-color", bg);
			});
			BlogIt.fn.showMsg(data);
		}
	}
}(jQuery);

