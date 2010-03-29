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

	$("#wikitext .blogit-comment-form").closest('form').validity(function(){ BlogIt.fn.commentRules(); });
	BlogIt.fn.commentSubmit($("#wikitext .blogit-comment-form").closest('form'),'add');  //user comments posted via ajax
	$.validity.patterns.entryDate = BlogIt.fmt['entry-date'];
	$("#wikiedit.blogit-blog-form form").validity(function() {
		$("#entrydate").match("entryDate");
		$("#entrytitle,#entryurl").assert(($("#entryurl").val() || $("#entrytitle").val()), BlogIt.fn.xl('Either enter a Blog Title or a Pagename'));
	});

	$("a[href*=action\=bi_ca],a[href*=action\=bi_cua]").live('click', function(e){
		e.preventDefault();
		BlogIt.fn.ajax({ success: function(data){ BlogIt.fn.commentStatus(e.target, data); }}, e);
	});
	$("a[href*=action\=bi_del]").live('click', function(e){ BlogIt.fn.deleteDialog(e); });
	$("a[href*=action\=bi_bip]").live('click', function(e){ BlogIt.fn.commentBlock(e); });
	$("a[href*=action\=bi_ce]").live('click', function(e){ BlogIt.fn.commentEdit(e,'edit'); });
	$("a[href*=action\=bi_cr]").live('click', function(e){ BlogIt.fn.commentEdit(e,'reply'); });
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
			ajax["url"] = ( typeof ajax.url == "function" ?ajax.url(e.target.href) :(ajax.url || e.target.href) );
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
				'delayTime': 3000
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
				o.href = o.href.replace("bi_cua", "bi_ca");
				$(o).html(BlogIt.fn.xl("approve"));
			}else{
				o.href = o.href.replace("bi_ca", "bi_cua");
				$(o).html(BlogIt.fn.xl("unapprove"));
			}
			var cc_Obj = $('a[href*=action=blogitadmin&s=unapproved-comments]');
			var cc_Txt = cc_Obj.html();
			cc_Obj.html( cc_Txt.replace(new RegExp(BlogIt.fn.xl('Unapproved Comments:')+' (\\d*)'), updateCount) );
		},
		commentEdit: function(e, mode){
			e.preventDefault();
			$.ajax({dataType:'json', url:e.target.href,  //get the comment form from pmwiki
				success: function(data){
					if (data.out){  //form returned in data.out
						$("#dialog").html(data.out).dialog('option', 'width', '500px').dialog("open");  //load the comment form into a dialog
						BlogIt.fn.commentSubmit($('#dialog form'), mode, e);
					}
				}
			});
		},
		commentSubmit: function(frm, mode, e){
			frm
				.prepend('<input type="hidden" value="ajax" name="bi_mode">')  //trigger ajax mode
				.bind("submit",function(){
					$.validity.start();
					BlogIt.fn.commentRules();
					var result = $.validity.end();
					// Return whether it's okay to proceed with the Ajax:
					if (result.valid){
						$.ajax({type: 'POST', dataType:'json', url:$(this).attr('action'),  //post with the action defined on the form
							data: $(this).serialize(),
							success: function(data){  //after PmForms finishes processing, update page with new content
								$('#dialog').dialog("close");
								var $new_id=$(data.out).find('[id^=bi_ID]');  //find the new object in the returned DOM
								if ($new_id.length!=1)  $new_id=$(data.out).filter('[id^=bi_ID]');  //needed for equilibrium, and similar skins, storing comments as non-LI
								if (mode=='reply'||mode=='add')  $('#blogit-comment-list').append($new_id);  //adding a new comment
								else  $('#'+$(e.target).closest('"[id^=bi_ID]"').attr('id')).replaceWith($new_id);  //update existing comment
								BlogIt.fn.flash($new_id, data);
								if (mode=='add' && data.result!='error')  frm[0].reset();
							}
						});
					}
					return false;  //ensure form doesn't do normal processing on Post
				});
		},
		commentRules: function(){
			$("#comment-author").require();
			$("#comment-email").require().match("email");
			$("#comment-website").match("url");
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

