// blogit.js 2010-08-12 1.6.0
jQuery.noConflict();
jQuery(document).ready(function($){
	$("<div/>").attr({id:"dialog"}).appendTo("body");
	$('#dialog').dialog({ resizable: true, modal: true, autoOpen: false, closeOnEscape: false });  //set defaults

	//show error messages set by pmwiki, in .wikimessage
	if ($('.wikimessage').length){ $('html,body').animate({scrollTop: $('.wikimessage').offset().top-175}, 1); }
	BlogIt.fn.showMsg({msg:$(BlogIt.pm['skin-classes']['blog-form']+' .wikimessage').html(), result:'error'});
	BlogIt.fn.showMsg({msg:$(BlogIt.pm['skin-classes']['comment-form']+' .wikimessage').html(), result:'success'}); //default to success, since no way to tell if error.

	$('#blogit-cancel').bind('click', function(){  //for blog entry, restore initial data to prevent validation errors from changed field.
		var $form = ($("#wikiedit").length ?$(BlogIt.pm['skin-classes']['blog-form']+' form') :$(BlogIt.pm['skin-classes']['comment-form']).closest('form'));  //on the read page or edit page
		$form[0].reset();  //assume we loaded with valid data.
		return true;
	});
	//adds ajax handler, and validation to forms already on the page (ie, comment form); need to exclude cancel/submit buttons on 'normal' comment edit
	BlogIt.fn.ajaxForm($(BlogIt.pm['skin-classes']['comment-list-wrapper']+' + form'), BlogIt.fn.commentRules, BlogIt.fn.commentSubmit, 'add');

	//add form validation to non-ajax forms (ie, Edit form in normal mode)
	$.validity.patterns.entryDate = BlogIt.fmt['entry-date'];
	$(BlogIt.pm['skin-classes']['blog-form']+' form').validity( BlogIt.fn.blogRules );

	$("a[href*=action\=bi_ca&bi_mode\=ajax],a[href*=action\=bi_cua&bi_mode\=ajax]").live('click', function(e){  //comment un/approve
		e.preventDefault();
		BlogIt.fn.ajax({ success: function(data){ BlogIt.fn.commentStatus(e.target, data); }}, e);
	});
	$("a[href*=action\=bi_be&bi_mode\=ajax],a[href*=action\=bi_ne&bi_mode\=ajax]").live('click', function(e){ BlogIt.fn.loadDialog(e,'blog'); });  //blog edit
	$("a[href*=action\=bi_del&bi_mode\=ajax]").live('click', function(e){ BlogIt.fn.deleteDialog(e); });  //delete comments and blogs
	$("a[href*=action\=bi_bip]").live('click', function(e){ BlogIt.fn.commentBlockIP(e); });  //block comment IP addresses
	$("a[href*=action\=bi_ce&bi_mode\=ajax]").live('click', function(e){ BlogIt.fn.loadDialog(e,'comment','edit'); });  //comment edit
	$("a[href*=action\=bi_cr&bi_mode\=ajax]").live('click', function(e){ BlogIt.fn.loadDialog(e,'comment','reply'); });  //comment reply (admins)
	$(BlogIt.pm['skin-classes']['blog-form']+' form :input:not(:submit)').bind('change', function(){  //if any field (not a submit button) changes...
		window.onbeforeunload = function(){ return BlogIt.fn.xl('You have unsaved changes.'); }
	});
	$(BlogIt.pm['skin-classes']['blog-form']+' form :input:submit').bind('click', function(){
		window.onbeforeunload = null;  //don't trigger on submit buttons.
	});

	BlogIt.fn.addTagEvents();
});

var BlogIt={ fmt:{}, xl:{}, fn:{}, pm:{} };
BlogIt.fn = function($){
//private declarations
	var _unapprove;
	$.ajaxSetup({ timeout: 15000,  //timeout of 15 seconds
		contentType: "application/x-www-form-urlencoded; charset="+BlogIt.pm['charset'],  //NOTE: jquery will always send with UTF8, regardless of charset specified.
		error: function(request,error){
			BlogIt.fn.showMsg({result:'error', msg:(
				(error=='parsererror' ?'Parsing JSON request failed.'
				:(error=='timeout' ?'Request timeout.'
				:'Error: '+error+"\n"+request.readyState+"\nresponseText: "+request.responseText
				))
			)});
		}
	});
	function isComment(e){ return e.hasClass( BlogIt.pm['skin-classes']['comment'].replace(/^\./,'') ); }
	function isCommentApproved(e){ return $("a[href*=action\=bi_cua&bi_mode\=ajax]", e).length > 0; }
	function updateCommentCount(approvedCC, unapprovedCC){
		function updateCC(e, c){
			var e_txt = e.text().replace(/\n/ig, '');  //remove extraneous \n as it messes up the replacing
			var cc = e_txt.match(/\d+/).join('');  //parse out the number from the link text (assume the only number there is the comment count)
			e.text( e_txt.replace(cc, (parseInt(cc)+c)));
		}
		$(BlogIt.pm['skin-classes']['approved-comment-count']).each(function(i,e){ updateCC($(e), approvedCC); });
		$(BlogIt.pm['skin-classes']['unapproved-comment-count']).each(function(i,e){ updateCC($(e), unapprovedCC); });
	}
	function getWrapper(e){ return $(e).closest('"[id^=bi_ID]"'); }
	function getActionContext(e, c){ return $(e).closest(c.join(',')); }
	function getSkinClass($e, c){  //return the skin-class of $e
		for (var i=0; ($e.length>0 && i<c.length); i++)  if ($e.bi_seek(c[i]).length > 0)  return c[i];
		return '';
	}
	function getEnteredIP(e){ return e+'&bi_ip='+$("#blogit_ip").val(); };
	function objectRemove(e, data){
		var $old = getWrapper(e);
		//if this is a comment, and if the comment was approved deduct approved-count, else deduct unapproved-comment
		if ( isComment($old) )  (isCommentApproved($old) ?updateCommentCount(-1, 0) :updateCommentCount(0, -1));
		$old.fadeOut(500, function(){ $(this).remove(); });
		BlogIt.fn.showMsg(data);
	};
	//dialog functions
	function dialogWait(clear){
		$("#dialog").siblings(".ui-dialog-titlebar").find(".ui-dialog-title")
			.css((clear ?{background:""} :{background: "url("+BlogIt.pm.pubdirurl+"/wait.gif) no-repeat left center", width:"18px", height:"18px"}));
	};
	function dialogClose(data){
		dialogWait(true);
		if (!data || (data && data.result!='error'))  $("#dialog").dialog("close").empty();
	};
	function dialogShow(txt, yes, no, w, ajax, e){
		var $d = $('#dialog');
		$d.html(txt).dialog('option', 'width', w);
		var btn={};
		if (no) btn[BlogIt.fn.xl(no)] = dialogClose;
		if (yes) btn[BlogIt.fn.xl(yes)] = function(){
			BlogIt.fn.ajax(ajax, e);
			dialogClose();
		};
		if (yes||no) $d.dialog('option', 'buttons', btn);
		$d.dialog('open');
	};
	//visuals
	function flash($e, data){
		var bg = $e.parent().css('background-color');
		$e.animate(
			{ backgroundColor: '#BBFFB6'},
			{ duration: 750, complete: function(){
				$(this).animate(
					{ backgroundColor: bg },
					{ duration:750, complete: function(){ $(this).css('background-color','') } }
			)}}
		);
		BlogIt.fn.showMsg(data);
	};

	//add this to jquery
	$.fn.bi_seek = function(seek){
		var $found;
		this.each(function(){
			var $this=jQuery(this);
			$found=$this.find(seek);
			if ($found.length<1)  $found=$this.filter(seek);
			if ($found.length==1)  return false;
		});
		return $found;
	};

//public functions
	return {
		deleteDialog: function(e){
			e.preventDefault();
			dialogShow(BlogIt.fn.xl('Are you sure you want to delete?'),'Yes','No','300px',
				{success:function(data){ objectRemove(e.target, data); }},e);
		},
		commentBlockIP: function(e){
			e.preventDefault();
			BlogIt.fn.ajax({
				success: function(data){
					if (data.ip){
						dialogShow(
							BlogIt.fn.xl('Commenter IP: ')+data.ip+'<br/>'+BlogIt.fn.xl('Enter the IP to block:')+
							'<input id="blogit_ip" type="text" value="'+data.ip+'"/>','Submit','Cancel','300px',
							{	url: function(e){ return getEnteredIP(e); },
								success: function(data){ BlogIt.fn.showMsg(data); }
							}, e);
					}
				}
			},e);
		},
		commentStatus: function(e, data){
			var $e = getWrapper(e);
			flash($e, data);
			_unapprove = ( $(e).html()==BlogIt.fn.xl('unapprove') );
			e.href = (_unapprove ?e.href.replace('bi_cua', 'bi_ca') :e.href.replace('bi_ca', 'bi_cua'));
			$(e).html(BlogIt.fn.xl( (_unapprove ?'approve' :'unapprove') ));
			$e.removeClass('blogit-comment-' +(!_unapprove ?'un' :'') +'approved').addClass('blogit-comment-' +(_unapprove ?'un' :'') +'approved')
			if (_unapprove)  updateCommentCount(-1,1)
			else  updateCommentCount(1,-1);
		},
		//opens a dialog with content from PmWiki
		loadDialog: function(e,name,mode){
			e.preventDefault();
			$.ajax({dataType:'json', url:e.currentTarget.href,  //get the comment form from pmwiki; not .target, because actual target might be an image wrapped in an anchor
				success: function(data){
					if (data.out){  //form returned in data.out
						var txt=(name=='blog' ?$(data.out).filter('#wikiedit') :data.out);  //only show wikiedit, not the editing reference
						var btn={};
						btn[BlogIt.fn.xl('Cancel')] = dialogClose;
						btn[BlogIt.fn.xl('Submit')] = function(){ $(this).find('form').submit(); };
						$('#dialog').html( txt )
							.dialog('option', 'buttons', btn)
							.dialog('option', 'width', (name=='blog'?'750px':'430px')).dialog('open');  //load the edit form into a dialog
						if (name=='blog')  BlogIt.fn.ajaxForm($('#dialog form'), BlogIt.fn.blogRules, BlogIt.fn.blogSubmit, mode, e);  //blog edit
						else if (name=='comment')  BlogIt.fn.ajaxForm($('#dialog form'), BlogIt.fn.commentRules, BlogIt.fn.commentSubmit, mode, e);  //comments
					}
				}
			});
		},
		//defines the actions to perform when clicking Submit/Cancel from dialogs, and comment form on entry form
		ajaxForm: function(frm, rulesFn, submitFn, mode, eventTarget){
			BlogIt.fn.addTagEvents();
			frm
				.prepend('<input type="hidden" name="bi_mode">')  //trigger ajax mode
				.bind('submit',function(e){
					$('[name=bi_mode]', frm).attr('value','ajax');  //IE8 resets value to null after one comment submit, since it's in the returned ajax.
					e.preventDefault();
					$.validity.start();
					rulesFn(frm);  //calls BlogIt.fn.blogRules or BlogIt.fn.commentRules
					var result = $.validity.end();  //if valid then it's okay to proceed with the Ajax
					if (result.valid){
						var $context,skinClass;
						if (eventTarget){  //eventTarget is null for user clicking Post button (mode=='add')
							//valid contexts. don't include 'comment-list' as it's the default, and also present on 'comment-admin-list'
							var vc = [BlogIt.pm['skin-classes']['blog-entry'],BlogIt.pm['skin-classes']['comment-admin-list'],
								BlogIt.pm['skin-classes']['blog-entry-summary'], BlogIt.pm['skin-classes']['blog-list-row']];
							$context = getActionContext(eventTarget.target, vc);
							skinClass = getSkinClass($context, vc);
							$(this).prepend('<input type="hidden" value="'+skinClass+'" name="bi_context">')  //trigger multi-entry mode
						}
						dialogWait();
						$.ajax({type: 'POST', dataType:'json', url:$(this).attr('action'),  //post with the action defined on the form
							data: $(this).serialize(),  //NOTE: jquery will always send with UTF8, regardless of charset specified.
							success: function(data){  //after PmForms finishes processing, update page with new content
								dialogClose(data);
								if (data.out)  submitFn(data, eventTarget, mode, frm, $context, skinClass);
								else  BlogIt.fn.showMsg({msg:(data.msg || BlogIt.fn.xl('No data returned.')), result:(data.result || 'error')});
							}
						});
					}
				});
		},
//Routines called from ajaxForm
		blogSubmit: function(data, eventTarget, mode, frm, $context, skinClass){  //eventTarget,mode,frm,$context,skinClass not used in this routine
			//can't use closest since no eventTarget on DOM passed back from server; use bi_seek (filter/find) to start from top of DOM, work down
			var $new=$(data.out).bi_seek(skinClass);  //class is "class1 class2", bi_seek (find/filter) needs ".class1.class2"
			$context.replaceWith($new);  //update existing blog entry
			flash($new, data);
		},
		commentSubmit: function(data, eventTarget, mode, frm, $context, skinClass){  //eventTarget is null for user clicking Post button (mode=='add')
			var firstComment = $(BlogIt.pm['skin-classes']['comment-list']).length==0;
			var $new = (firstComment ?$(data.out).bi_seek('[id^=bi_ID]').parent() :$(data.out).bi_seek('[id^=bi_ID]'));
			if (data.result!='error'){
				var newCommentApproved = isCommentApproved($new);
				if (mode=='edit'){
					var $old = getWrapper(eventTarget.target);  //find main wrapper from click point
					$old.replaceWith($new);  //update existing comment
					if (newCommentApproved != isCommentApproved($old))  (newCommentApproved ?updateCommentCount(1,-1) :updateCommentCount(-1,1));
				}else{  //add or reply
					if (mode=='add')  frm[0].reset();
					$(BlogIt.pm['skin-classes'][(firstComment ?'comment-list-wrapper' :'comment-list')]).append($new);  //adding a new comment
					//recreate a new capcha code to prevent multiple submits
					$(BlogIt.pm['skin-classes']['comment-submit']+' img[src*=action\=captchaimage]').replaceWith($("img[src*=action\=captchaimage]", data.dom));
					$(BlogIt.pm['skin-classes']['comment-submit']+' input[name=captchakey]').replaceWith($('input[name=captchakey]', data.dom));
					(newCommentApproved ?updateCommentCount(1,0) :updateCommentCount(0,1))
				}
			}
			flash($new, data);
		},
		commentRules: function(frm){
			$('#comment-author',frm).require();
			$('#comment-email',frm).require().match('email');
			$('#comment-website',frm).match('url');
		},
		blogRules: function(frm){
			frm = frm || BlogIt.pm['skin-classes']['blog-form']+' form';
			$('#entrydate',frm).match('entryDate');
			var url_val = $('#entryurl',frm).val();
			$('#entrytitle,#entryurl',frm)
				.assert(	( (url_val && !url_val.match(/^.*?\.$/)) || $('#entrytitle',frm).val()),
					BlogIt.fn.xl('Either enter a Blog Title or a Pagename')
				);
		},
		addTagEvents: function(){
			//Add autocomplete. :not only adds autocomplete if not already added.
			function split(val) { return val.split(/,\s*/);	}
			function extractLast(term) { return split(term).pop(); }

			$("#entrytags:not(.ac_input)").autocomplete({
				minLength: 0,
				source: function(request, response) {
					// delegate back to autocomplete, but extract the last term
					response($.ui.autocomplete.filter(BlogIt.pm.categories.split(','), extractLast(request.term)));
				},
				focus: function() {
					// prevent value inserted on focus
					return false;
				},
				select: function(event, ui) {
					var terms = split( this.value );
					// remove the current input
					terms.pop();
					// add the selected item
					terms.push( ui.item.value );
					// add placeholder to get the comma-and-space at the end
					terms.push("");
					this.value = terms.join(", ");
					return false;
				}
			});
			$('#entrytags').live('blur', function(e){ $this=$(this); $this.val($this.val().replace(/[,|\s]+$/,"")); });
		},
//Visuals
		showMsg: function(data){
			if (data.msg)  $('body').showMessage({
				'thisMessage':[BlogIt.fn.xl(data.msg)],
				'className': data.result,
				'opacity': 95,
				'displayNavigation':	(data.result=='error' ?true :false),
				'autoClose': (data.result=='error' ?false :true),
				'delayTime': BlogIt.pm['ajax-message-timer']
			});
		},
//Utilities
		xl: function(t){ return ( (BlogIt.xl[t] ?$('<div>'+BlogIt.xl[t]+'</div>').html() :t) ); },
		ajax: function(ajax, e){
			ajax['dataType'] = ajax.dataType || 'json';
			ajax['url'] = ( typeof ajax.url == 'function' ?ajax.url(e.target.href) :(ajax.url || e.target.href) );
			ajax['context'] = ajax.context || e.target;
			$.ajax(ajax);
		}
	};
}(jQuery);

