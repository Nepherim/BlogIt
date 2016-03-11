// blogit.js 2016-03-24 1.9.0
jQuery.noConflict();
jQuery(document).ready(function($){
	//show error messages set by pmwiki in .wikimessage
	//TODO: Hide original message in .wikimessage?
	BlogIt.fn.showMsg({msg:$(BlogIt.pm['skin-classes']['blog-form']+' .wikimessage').html(), result:'error'});
	BlogIt.fn.showMsg({msg:$(BlogIt.pm['skin-classes']['comment-form']+' .wikimessage').html(), result:'success'}); //default to success, since no way to tell if error.

	$('#blogit-cancel').addClass('cancel');  //for blog entry add class to prevent validations preventing cancel action
	BlogIt.fn.addValidation();
	BlogIt.fn.addAutocomplete();

	$(document).on('click', '.bi-link-comment-unapproved,.bi-link-comment-approved', function(e){ BlogIt.fn.ajax({ success: function(data){ BlogIt.fn.flipCommentStatus(e.target, data); }}, e); });
	$(document).on('click', '.bi-link-blog-new,.bi-link-blog-edit,.bi-link-comment-edit,.bi-link-comment-reply', function(e){ BlogIt.fn.showDialog(e); });
	//TODO: Is there actually a blog delete function?
	$(document).on('click', '.bi-link-comment-delete,.bi-link-blog-delete', function(e){ BlogIt.fn.showDelete(e); });  //delete comments and blogs
	$(document).on("click", '.bi-link-comment-block', function(e){ BlogIt.fn.showBlockIP(e); });  //block comment IP addresses
	$(BlogIt.pm['skin-classes']['blog-form']+' form :input:not(:submit)').on('change',   //if any field (not a submit button) changes...
		function(){	$(window).on('beforeunload', function(){ return BlogIt.fn.xl('You have unsaved changes.'); }); });
});

var BlogIt={ fmt:{}, xl:{}, fn:{}, pm:{} };
BlogIt.fn = function($){
//private declarations
//TODO: When are these used? fn.ajax?
	$.ajaxSetup({ timeout: 15000,  //timeout of 15 seconds
		//jquery will always send with UTF8, regardless of charset specified.
		contentType: "application/x-www-form-urlencoded",
		error: function(request,error){
			BlogIt.fn.showMsg({result:'error', msg:(
				(error=='parsererror' ?'Parsing JSON request failed.'
				:(error=='timeout' ?'Request timeout.'
				:'Error: '+error+"\n"+request.readyState+"\nresponseText: "+request.responseText
				))
			)});
		}
	});
	var dialog;  //global dialog reference so we can close from ajaxSubmit()

	function updateCommentCount(approvedCC, unapprovedCC){
		function updateCC(e, c){
			var e_txt = e.text().replace(/\n/ig, '');  //remove extraneous \n as it messes up the replacing
			var cc = e_txt.match(/\d+/).join('');  //parse out the number from the link text (assume the only number there is the comment count)
			e.text( e_txt.replace(cc, (parseInt(cc)+c)));
		}
		$(BlogIt.pm['skin-classes']['approved-comment-count']).each(function(i,e){ updateCC($(e), approvedCC); });
		$(BlogIt.pm['skin-classes']['unapproved-comment-count']).each(function(i,e){ updateCC($(e), unapprovedCC); });
	}
	//Search up for a wrapper with a bi_ID (bi_seek searches down)
	function getIDWrapper(target){
		var found=$(target).closest('[id^="bi_ID"]');
		return (found.length ?found :null);
	}
	function closestTemplateObject($target){
		//Find the class which represents the pagelist template we should use, based on where user clicked
		var vc = [BlogIt.pm['skin-classes']['blog-entry'], BlogIt.pm['skin-classes']['comment-admin-list'], BlogIt.pm['skin-classes']['blog-entry-summary'],
			BlogIt.pm['skin-classes']['blog-list-row'], BlogIt.pm['skin-classes']['comment-list']];
		var closest = $target.closest(vc.join(','));
		console.log('closest: '+(closest ?'found' :'not  found'));
		console.log('class: '+closest.attr("class"));
		console.log(closest);
		return (closest.length ?closest :null);  //when clicking ajax new entry
	}
	//removed comments or blog posts (from blog grid)
	function objectRemove(e, data){
		var $old = getIDWrapper(e.target);
		//if this is a comment, and if the comment was approved deduct approved-count, else deduct unapproved-comment
		if ( $old.hasClass( BlogIt.pm['skin-classes']['comment'].replace(/^\./,'')) )
			($('a', $old).hasClass('blogit-comment-approved') ?updateCommentCount(-1, 0) :updateCommentCount(0, -1));
		$old.fadeOut(500, function(){ $(this).remove(); });
		BlogIt.fn.showMsg(data);
	};
	//dialog functions
	function dialogWait(clear){
		$('.jBox-title div:not(.jBox-closeButton)').css( clear ?{background:""} :{background: "url( "+ BlogIt.pm.pubdirurl+ "/wait.gif) no-repeat left center", width: "18px", height: "18px"});
	};
	function dialogShow(txt, yes, no, w, ajax, e){
		e.preventDefault();
		var prompt=new jBox('Confirm',{
			content: txt,
			_onOpen: function() {  //Override jbox default. Only change is to prevent dialog closing post confirm() so we manually close if form validates.
				this.submitButton.off('click.jBox-Confirm' + this.id).on('click.jBox-Confirm' + this.id, function() { this.options.confirm ? this.options.confirm() : eval(this.source.data('jBox-Confirm-submit')); }.bind(this));
			},
			confirmButton: BlogIt.fn.xl(yes),
			cancelButton: BlogIt.fn.xl(no),
			confirm: function(){ BlogIt.fn.ajax(ajax, e); prompt.close(); },
			onCloseComplete: function () { this.destroy(); },
			width: w, minWidth: w, maxWidth: w  //needed to override jbox default
		}).open();
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
	$.validator.addMethod(
		'datetime',
		function(v, e, fmt){
			return this.optional(e) ||	RegExp(BlogIt.fmt['entry-date']).test(v);
		},
		'Must be datetime.'  //TODO: Add format string XL
	);
	//add this to jquery: need to find objects at same level, or below. So do a find() followed by a filter()
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
	//Direct copy from jquery.validate/additional-methods.min.js, so we don't have to include entire file for single function
	$.validator.addMethod( "require_from_group", function( value, element, options ) {
		var $fields = $( options[ 1 ], element.form ),
			$fieldsFirst = $fields.eq( 0 ),
			validator = $fieldsFirst.data( "valid_req_grp" ) ? $fieldsFirst.data( "valid_req_grp" ) : $.extend( {}, this ),
			isValid = $fields.filter( function() {
				return validator.elementValue( this );
			} ).length >= options[ 0 ];

		// Store the cloned validator for future validation
		$fieldsFirst.data( "valid_req_grp", validator );

		// If element isn't being validated, run each require_from_group field's validation rules
		if ( !$( element ).data( "being_validated" ) ) {
			$fields.data( "being_validated", true );
			$fields.each( function() {
				validator.element( this );
			} );
			$fields.data( "being_validated", false );
		}
		return isValid;
	}, $.validator.format( "Please fill at least {0} of these fields." ) );  //TODO: XL()

	//defines the ajax actions when clicking Submit from dialogs, and Submit from comment entry
	function ajaxSubmit($frm, submitFn, e){
		dialogWait();
		//trigger ajax mode; prevent duplicates which could occur if multiple comments submitted
		if (!$('[name="bi_mode"]',$frm).length)  $frm.prepend('<input type="hidden" name="bi_mode" value="ajax">');

		//$context is a JQ object we're going to replace; templateClass is used in php.bi_AjaxRedirect to determine which includesection template to use
		var $context,templateClass,target;
		if (e){
			console.log('clicked: '+($(e.target).is('img') ?'img' :'link'));
			console.log(e.currentTarget);
			target = ( $(e.target).is('img') ?e.currentTarget :e.target);  //if user clicked img, bubble out to currentTarget to find href link
			console.log('href: '+target.href);
			var $closest=closestTemplateObject($(target));
			//Clicking reply from admin list templateClass is ".blogit-comment-list blogit-comment-admin-list" since container has two classes, use only the first
			templateClass = ($closest ?'.'+ $closest.attr("class").split(' ')[0] :'');  //no closest when adding new entry from ajax link
			$('.jBox-content form').prepend('<input type="hidden" name="bi_context" value="'+ templateClass+ '">')  //tell pmwiki which template to use, based on class

			//Find the blog/comment entry that the action relates to, which is either something with an ID of bi_ID, or an element with a template class
			console.log('target wrapper: ');
			console.log(getIDWrapper(target));
			$context = $( getIDWrapper(target) || $closest);
		} else {
			console.log('no e');  //e is null for user clicking Post button ('ca')
		}
		console.log('templateClass: '+templateClass);
		console.log ('$context:');
		console.log ($context);
		console.log('url: '+$frm.attr('action'));
		//TODO: Why not fn.ajax
		$.ajax({type: 'POST', dataType:'json', url:$frm.attr('action'),
			data: $frm.serialize(),  //NOTE: jquery will always send with UTF8, regardless of charset specified.
			success: function(data){  //after PmForms finishes processing, update page with new content
				console.log('closing');
				//TODO: Check needed, or just close?
				if (!data || (data && data.result!='error'))  if (dialog)  dialog.close();  //TODO: Need more robust check. dialog doesn't exist when submitting comments; why not dialogClose()
				if (data.out)  submitFn(data, target, $context, templateClass);  //TODO: templateClass not defined from edit comment
				//TODO: XL('error')
				else  BlogIt.fn.showMsg({msg:(data.msg || BlogIt.fn.xl('No data returned.')), result:(data.result || 'error')});
			}
		});
	}
//Routines called from ajaxSubmit
	function updateBlog(data, target, $context, templateClass){
		//can't use closest since no e on DOM passed back from server; use bi_seek (filter/find) to start from top of DOM, work down
		//Can't use entire data.out, as pmwiki returns full html objects, which may include <table> tags, not just the <tr>
		var $new=$(data.out).bi_seek(templateClass);
		$context.replaceWith($new);  //update existing blog entry
		flash($new, data);
	}
	function updateComment(data, target, $context, templateClass){  //data.out is the full #blogit-commentblock which includes 'Comment" header, and a single comment
		console.log('updating comment: '+templateClass);
		console.log($context);
		if (data.result!='error'){
			console.log('no error');
			var firstComment = $(BlogIt.pm['skin-classes']['comment-list']).length==0;
			console.log('first comment: '+firstComment);
			var $new = (firstComment ?$(data.out) :$(data.out).bi_seek('[id^="bi_ID"]'));  //if this is the first comment then include entire data.out
			var newCommentApproved = $new.hasClass('blogit-comment-approved');
			if (!target || $(target).is('.bi-link-comment-reply') ){  //comment add (!target) or comment reply
				if (!target){  //comment add
					console.log('comment add');
					$(BlogIt.pm['skin-classes']['comment-list-wrapper']+ '+form')[0].reset();  //Reset the comment form adjacent to the wrapper since we just submitted it
					//recreate a new capcha code to prevent multiple submits
					$(BlogIt.pm['skin-classes']['comment-submit']+' img[src*="action\=captchaimage"]').replaceWith($('img[src*="action\=captchaimage"]', data.dom));
					$(BlogIt.pm['skin-classes']['comment-submit']+' input[name="captchakey"]').replaceWith($('input[name="captchakey"]', data.dom));
					(newCommentApproved ?updateCommentCount(1,0) :updateCommentCount(0,1))
				}
				//if first comment append to wrapper, otherwise add to list; if we have a context use it (on admin page where replying to a comment)
				console.log('closest: '+(target?'target':'no target'));
				console.log((target ?closestTemplateObject($context) :'comment-list'));
				$(target ?closestTemplateObject($context) :BlogIt.pm['skin-classes'][(firstComment ?'comment-list-wrapper' : 'comment-list')] ).append($new);
			}else if ($(target).is('.bi-link-comment-edit')){  //comment edit
				console.log ('new id: '+$new.attr('id'));
				$context.replaceWith($new);
				if ( newCommentApproved != $context.hasClass('blogit-comment-approved') )  (newCommentApproved ?updateCommentCount(1,-1) :updateCommentCount(-1,1));
			}
		}
		flash($new, data);
	}

//public functions
	return {
		showDelete: function(e){
//TODO: Required? Why not required for showBlockIP?
//			e.preventDefault();
			//TODO: yes and no with XL()
			dialogShow(BlogIt.fn.xl('Are you sure you want to delete?'),'Yes','No',300,
				{success:function(data){ objectRemove(e, data); }},e);
		},
		showBlockIP: function(e){
			BlogIt.fn.ajax({
				success: function(data){
					if (data.ip){
						dialogShow(
							BlogIt.fn.xl('Commenter IP: ')+data.ip+'<br/>'+BlogIt.fn.xl('Enter the IP to block:')+
							//TODO: submit, Cancel with XL()
							'<input id="blogit_ip" type="text" value="'+data.ip+'"/>','Submit','Cancel',300,
							{	url: function(e){ return e+'&bi_ip='+$("#blogit_ip").val(); },
								success: function(data){ BlogIt.fn.showMsg(data); }
							}, e);
					}
				}
			},e);
		},
		flipCommentStatus: function(target, data){
			var $wrapper = getIDWrapper(target);
			flash($wrapper, data);
			var approved = $(target).hasClass('bi-link-comment-approved');
			target.href = (approved ?target.href.replace('bi_cua', 'bi_ca') :target.href.replace('bi_ca', 'bi_cua'));
			$(target).html(BlogIt.fn.xl( (approved ?'approve' :'unapprove') ));
			$wrapper.removeClass('blogit-comment-' +(!approved ?'un' :'') +'approved').addClass('blogit-comment-' +(approved ?'un' :'') +'approved');
			$(target).removeClass('bi-link-comment-' +(!approved ?'un' :'') +'approved').addClass('bi-link-comment-' +(approved ?'un' :'') +'approved');
			if (approved) updateCommentCount(-1,1);
			else updateCommentCount(1,-1);
		},
		//opens a dialog with content from PmWiki, calls addValidation(), and then on submit calls ajaxSubmit(), which calls updateBlog/updateComment
		showDialog: function(e){
			e.preventDefault();
			//TOTO: Why not fn.ajax
			$.ajax({
				dataType:'json',
				url:e.currentTarget.href,  //get the comment form from pmwiki; not .target, because actual target might be an image wrapped in an anchor
				success: function(data){
					if (data.out){  //form content returned in data.out
						var blog=$(e.currentTarget).is('.bi-link-blog-edit,.bi-link-blog-new');  //are we doing some blog related action?
						console.log('blog action: '+blog);
						dialog = new jBox('Confirm', {
							title: '&nbsp',  //make the title bar appear just for visual
							content: (blog ?$(data.out).filter('#wikiedit') :$(data.out)),  //remove the pmwiki editting help text
							_onOpen: function() {  //Override jbox default. Only change is to prevent dialog closing post confirm() so we manually close if form validates.
								this.submitButton.off('click.jBox-Confirm' + this.id).on('click.jBox-Confirm' + this.id, function() { this.options.confirm ? this.options.confirm() : eval(this.source.data('jBox-Confirm-submit')); }.bind(this));
							},
							closeButton: 'title',
							confirm: function (ev) { $('.jBox-content form').submit(); },  //will call ajaxSubmit(), set by call to addValidation below
							//TODO: XL()
							confirmButton: 'Submit',
							cancelButton: 'Cancel',
							onCloseComplete: function () { this.destroy(); },
							width: (blog?750:430), minWidth: (blog?750:430), maxWidth: 10000  //needed to override jbox default
						})
						.open();
						BlogIt.fn.addAutocomplete();
						BlogIt.fn.addValidation(e);  //adds submit handler for button in dialog
					}
				}
			});
		},
		addValidation: function(e){
			console.log ('form: '+$(BlogIt.pm['skin-classes']['blog-form']+ ' form').length);
			console.log($(BlogIt.pm['skin-classes']['blog-form']+ ' form'));
			$(BlogIt.pm['skin-classes']['blog-form']+ ' form').each(function(){
				console.log('setting up edit validations');
				$(this).validate({
					submitHandler: function(form) {  //Only if the form validates
						console.log('submitHandler');
						console.log('dialog: '+$(form).parents('.jBox-content').length);
						if ($(form).parents('.jBox-content').length){
							console.log('calling ajax form');
							ajaxSubmit($(form), updateBlog, e);
						}else{
							$(window).off('beforeunload');
							console.log('calling normal form');
							form.submit();
						}
					},
					rules: {
						ptv_entrydate: {datetime: true},
						ptv_entryurl: {require_from_group: [1, 'input[name="ptv_entrytitle"],input[name="ptv_entryurl"]']},
						ptv_entrytitle: {require_from_group: [1, 'input[name="ptv_entrytitle"],input[name="ptv_entryurl"]']}
					}
				});
			});

			//dialog comment form is not wrapped in class
			console.log('form selector: '+'.jBox-content form,'+ BlogIt.pm['skin-classes']['comment-list-wrapper']+ '+form');
			$('.jBox-content form,'+ BlogIt.pm['skin-classes']['comment-list-wrapper']+ '+form').each(function(){
				console.log('setting up comment validations');
				$(this).validate({
					submitHandler: function(form) {
						console.log('calling comment ajax form');
						ajaxSubmit($(form), updateComment, e);  //mode is undefined when normal comment add, since no onclick handler defined
					},
					rules: {
						ptv_commentauthor: {required: true},
						ptv_email: {required: true, email: true},
						ptv_website: {url: true}
					}
				});
			});
		},
		addAutocomplete: function(){
			//Add autocomplete. :not only adds autocomplete if not already added.
			$('input[name="ptv_entrytags"]').each( function(){
				console.log(this);
				new Awesomplete( this, {
					list: BlogIt.pm.categories.split(','),
					autoFirst: true,
					//allow multiple comma separated
					filter: function(text, input) {
						return Awesomplete.FILTER_CONTAINS(text, input.match(/[^,]*$/)[0]);
					},
					replace: function(text) {
						var before = this.input.value.match(/^.+,\s*|/)[0];
						this.input.value = before + text + ", ";
					}
				});
			});
			//remove pmwiki tag characters [], and remove final comma
			$(document).on("blur", '#entrytags', function(e){ $this=$(this); $this.val($this.val().replace(/[,|\s]+$/,"")); });
		},
//Visuals
		showMsg: function(data){  //data{msg, result}
			if (data.msg)
				new jBox('Notice', {
					content: BlogIt.fn.xl(data.msg),
					addClass: (data.result=='error' ?'error' :'success'),
					closeButton:	(data.result=='error' ?true :false),
					closeOnClick: (data.result=='error' ?'box' :null),
					closeOnEsc: (data.result=='error' ?true :false),
					autoClose: (data.result=='error' ?false :BlogIt.pm['ajax-message-timer']),
					position: {x: 'left', y: 'top'}
				}
			);
		},
//Utilities
		xl: function(t){ return ( (BlogIt.xl[t] ?$('<div>'+BlogIt.xl[t]+'</div>').html() :t) ); },
		ajax: function(ajax, e){
			e.preventDefault();
			ajax['dataType'] = ajax.dataType || 'json';
			ajax['url'] = ( typeof ajax.url == 'function' ?ajax.url(e.target.href) :(ajax.url || e.target.href) );
			ajax['context'] = ajax.context || e.target;
			$.ajax(ajax);
		}
	};
}(jQuery);

