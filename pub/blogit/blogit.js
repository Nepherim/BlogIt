// blogit.js 2016-03-27 1.9.0
jQuery.noConflict();
jQuery(document).ready(function($){
	//show error messages set by pmwiki in .wikimessage
	BlogIt.fn.showMsg({msg:$(BlogIt.pm['skin-classes']['blog-form']+' .wikimessage').html(), result:'error'});
	BlogIt.fn.showMsg({msg:$(BlogIt.pm['skin-classes']['comment-form']+' .wikimessage').html(), result:'success'}); //default to success, since no way to tell if error.
	BlogIt.fn.addValidation();
	BlogIt.fn.addAutocomplete();

	if (window.location.href.match(/action=bi_admin/))  BlogIt.pm['skin-classes']['comment-tag']='li';  //assume admin page is always LI
	//Classes are added by bi_Link(), so can be hard-coded.
	$(document).on('click', '.bi-link-comment-unapproved[href*="bi_mode=ajax"],.bi-Comment-Approve', function(e){ BlogIt.fn.adminAction(e, 'approve'); });  //action approve
	$(document).on('click', '.bi-link-comment-approved[href*="bi_mode=ajax"],.bi-Comment-Unapprove', function(e){ BlogIt.fn.adminAction(e, 'unapprove'); });  //action unapprove
	//TODO: Due to pmwiki bug where classes on last link override earlier links on the same line, need to check 'bi_mode=ajax' (ref php.bi_Link())
	$(document).on('click', '.bi-link-blog-new[href*="bi_mode=ajax"],.bi-link-blog-edit[href*="bi_mode=ajax"],'+
		'.bi-link-comment-edit[href*="bi_mode=ajax"],.bi-link-comment-reply', function(e){ BlogIt.fn.showEdit(e); });
	$(document).on('click', '.bi-link-comment-delete[href*="bi_mode=ajax"],.bi-link-blog-delete[href*="bi_mode=ajax"],.bi-Comment-Delete', function(e){ BlogIt.fn.adminAction(e,'delete'); });  //delete comments and blogs
	$(document).on("click", '.bi-link-comment-block,.bi-Comment-Block', function(e){ BlogIt.fn.adminAction(e,'block'); });  //block comment IP addresses
	$(document).on("click", '.bi-Comment-AllNone', function(e){ BlogIt.fn.toggleCheckboxes(e); });
	$(document).on("click", BlogIt.pm['skin-classes']['comment']+'.blogit-admin', function(e){ if ( !$(e.target).is('a,input') )  BlogIt.fn.commentAdminCheckbox(this, 'flip'); });
	$(document).on({  //hover doesn't cope with dynamically added elements
		mouseenter: function(){ BlogIt.fn.commentAdminCheckbox(this, 'show', false)},
		mouseleave: function(){BlogIt.fn.commentAdminCheckbox(this, 'hide', true)}},
		BlogIt.pm['skin-classes']['comment']+'.blogit-admin');
	//add admin-user functions on admin-page page titles and on single page Comment header
	$(BlogIt.pm['skin-classes']['comment-summary-title']+','+BlogIt.pm['skin-classes']['comment-block-title']+'.blogit-admin')
		.append($('<span class="blogit-cam-marker" />').html('&#9660'))  //add down arrow character to serve as menu marker,
		.jBox('Tooltip', {  //add admin menu
			trigger: 'mouseenter',
			//TODO: Better than hardcoding
			content:'<ul class="blogit-comment-admin-menu">'+
				'<li class="bi-Comment-AllNone">All</li>'+
				'<li class="bi-Comment-Approve">Approve</li><li class="bi-Comment-Unapprove">Unapprove</li>'+
				'<li class="bi-Comment-Block">Block</li>'+
				'<li class="bi-Comment-Delete">Delete</li>',
			pointer: 'left',
			position: {x: 'left', y: 'bottom'},
			offset:{x:50,y:-5},
			closeOnMouseleave: true,
			onOpen: function(){ this.source.addClass('bi-menu-hover'); },
			onClose: function(){ this.source.removeClass('bi-menu-hover'); }
		});
	$(BlogIt.pm['skin-classes']['blog-form']+' form :input:not(:submit)').on('change',   //if any field (not a submit button) changes...
		function(){	$(window).on('beforeunload', function(){ return BlogIt.fn.xl('You have unsaved changes.'); }); });
});

var BlogIt={ fmt:{}, xl:{}, fn:{}, pm:{} };
BlogIt.fn = function($){
//private declarations
	$.ajaxSetup({
		timeout: 15000,  //timeout of 15 seconds
		dataType:'json',
		contentType: "application/x-www-form-urlencoded",  //jquery will always send with UTF8, regardless of charset specified.
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
			var e_txt = e.text().replace(/\n/g, '');  //remove extraneous \n as it messes up the replacing
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
	function getCommentBlock(){  //Returns the block of comments for the hovered heading.
		var src = $('.bi-menu-hover').next('ol.blogit-comment-admin-list');  //TODO: assumes admin list is always OL
		return ( src.length>0 ?src :$(BlogIt.pm['skin-classes']['comment-list-block']) );  //get comment block for title admin is on
	}
	function closestTemplateObject($target){
		//Find the class which represents the pagelist template we should use, based on where user clicked
		var vc = [BlogIt.pm['skin-classes']['blog-entry'], BlogIt.pm['skin-classes']['comment-admin-list'], BlogIt.pm['skin-classes']['blog-entry-summary'],
			BlogIt.pm['skin-classes']['blog-list-row'], BlogIt.pm['skin-classes']['comment-list']];
		var closest = $target.closest(vc.join(','));
		return (closest.length ?closest :null);  //when clicking ajax new entry
	}
	//removed comments or blog posts (from blog grid)
	function objectRemove(e, data){
		$(e).each(function(i){
			var $old = getIDWrapper(this);
			//if this is a comment, and if the comment was approved deduct approved-count, else deduct unapproved-comment
			if ( $old.hasClass( BlogIt.pm['skin-classes']['comment'].replace(/^\./,'')) )
				($('a', $old).hasClass('blogit-comment-approved') ?updateCommentCount(-1, 0) :updateCommentCount(0, -1));
			$old.fadeOut(500, function(){ $(this).remove(); });
		});
		BlogIt.fn.showMsg(data);
	}
	function flipCommentStatus(target, action){
		action = (action || 'flip');
		$(target).each(function(i){
			var $wrapper = getIDWrapper(this);
			flash($wrapper);
			var approved = $(this).hasClass('bi-link-comment-approved');
			if (action=='flip' || (action=='unapprove'&&approved) || (action=='approve'&&!approved)){
				this.href = (approved ?this.href.replace('action=bi_cua', 'action=bi_ca') :this.href.replace('action=bi_ca', 'action=bi_cua'));
				$(this).html(BlogIt.fn.xl( (approved ?'approve' :'unapprove') ));
				$wrapper.removeClass('blogit-comment-' +(!approved ?'un' :'') +'approved').addClass('blogit-comment-' +(approved ?'un' :'') +'approved');
				$(this).removeClass('bi-link-comment-' +(!approved ?'un' :'') +'approved').addClass('bi-link-comment-' +(approved ?'un' :'') +'approved');
				if (approved) updateCommentCount(-1,1);
				else updateCommentCount(1,-1);
			}
		});
	}
	//dialog functions
	function dialogWait(clear){
		$('.jBox-title div:not(.jBox-closeButton)')
			.css( clear ?{background:""} :{background: "url( "+ BlogIt.pm.pubdirurl+ "/wait.gif) no-repeat left center", width: "18px", height: "18px"});
	};
	function dialogShow(txt, yes, no, w, ajax){
		//TODO: Use single variable/declaration for prompt and dialog
		var prompt=new jBox('Confirm',{
			content: txt,
			_onOpen: function() {  //Override jbox default. Only change is to prevent dialog closing post confirm() so we manually close if form validates.
				this.submitButton.off('click.jBox-Confirm' + this.id).on('click.jBox-Confirm' + this.id, function() { this.options.confirm ? this.options.confirm() : eval(this.source.data('jBox-Confirm-submit')); }.bind(this));
			},
			confirmButton: BlogIt.fn.xl(yes),
			cancelButton: BlogIt.fn.xl(no),
			confirm: function(){ BlogIt.fn.ajax(ajax); prompt.close(); },
			onCloseComplete: function () { this.destroy(); },
			width: w, minWidth: w, maxWidth: w  //needed to override jbox default
		}).open();
	}
	function flash($e){
		$e.delay(100).css("-webkit-transition","all 0.6s ease")
		.css("backgroundColor","white")
		.css("-moz-transition","all 0.6s ease")
		.css("-o-transition","all 0.6s ease")
		.css("-ms-transition","all 0.6s ease")
		.css("backgroundColor","#ACACAC").delay(200).queue(function() {
			$(this).css("backgroundColor","white");
			$(this).dequeue(); //Prevents box from holding color with no fadeOut on second click.
		});
	}
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
	}
	$.validator.addMethod('datetime', function(v, e, fmt){
		return this.optional(e) ||	RegExp(BlogIt.fmt['entry-date']).test(v);
	},	BlogIt.xl['Must be a datetime.']);  //TODO: Can't BlogIt.fn.xl() since fn not yet declared at this point.
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
			});
			$fields.data( "being_validated", false );
		}
		return isValid;
	}, $.validator.format( "Please fill at least {0} of these fields." ) );  //Can't put this format in XL

	//defines the ajax actions when clicking Submit from dialogs, and Submit from comment entry
	function ajaxSubmit($frm, submitFn, e){
		//$context is a JQ object we're going to replace; templateClass is used in php.bi_AjaxRedirect to determine which includesection template to use
		var $context,templateClass,target;
		dialogWait();
		//trigger ajax mode; prevent duplicates which could occur if multiple comments submitted
		if (!$('[name="bi_mode"]',$frm).length)  $frm.prepend('<input type="hidden" name="bi_mode" value="ajax">');
		if (e)  target = ( $(e.target).is('img') ?e.currentTarget :e.target);  //if user clicked img, bubble out to currentTarget to find href link
		if ($('[name="action"]',$frm).val()=='pmform' && (!e || target.href.match(/action=bi_(cr|ce|be|ne)/)) && !$('[name="bi_frm_action"]',$frm).length)
			$frm.prepend('<input type="hidden" name="bi_frm_action" value="'+ (!e ?'ca' :target.href.match(/action=bi_(cr|ce|be|ne)/)[1])+ '">')
		if (e){  //e is null for user clicking comment add Post button
			var $closest=closestTemplateObject($(target));
			//Clicking reply from admin list templateClass is ".blogit-comment-list blogit-comment-admin-list" since container has two classes, use only the first
			templateClass = ($closest ?'.'+ $closest.attr("class").split(' ')[0] :'');  //no closest when adding new entry from ajax link
			//tell pmwiki which template to use, based on class
			if (!$('[name="bi_context"]',$frm).length)  $frm.prepend('<input type="hidden" name="bi_context" value="'+ templateClass+ '">')
			//Find the blog/comment entry that the action relates to, which is either something with an ID of bi_ID, or an element with a template class
			$context = $( getIDWrapper(target) || $closest);
		}
		BlogIt.fn.ajax({
			method: 'POST',
			url:$frm.attr('action'),
			data: $frm.serialize(),  //NOTE: jquery will always send with UTF8, regardless of charset specified.
			success: function(data){  //after PmForms finishes processing, update page with new content
				if (!data || (data && data.result!='error'))
					if (dialog)  dialog.close();  //dialog doesn't exist when submitting comments
				if (data.out)
					submitFn(data, target, $context, templateClass);  //TODO: templateClass not defined from edit comment
				else
					BlogIt.fn.showMsg({msg:(data.msg || BlogIt.fn.xl('No data returned.')), result:(data.result || 'error')});
			}
		});
	}
//Routines called from ajaxSubmit
	function updateBlog(data, target, $context, templateClass){
		//can't use closest since no e on DOM passed back from server; use bi_seek (filter/find) to start from top of DOM, work down
		//Can't use entire data.out, as pmwiki returns full html objects, which may include <table> tags, not just the <tr>
		var $new=$(data.out).bi_seek(templateClass);
		$context.replaceWith($new);  //update existing blog entry
		flash($new);
		BlogIt.fn.showMsg(data);
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
		flash($new);
		BlogIt.fn.showMsg(data);
	}

//public functions
	return {
		toggleCheckboxes: function(e){  //e is the clicked menu item event
			var $src=$(e.target);
			var $blk=getCommentBlock();
			$blk.children(BlogIt.pm['skin-classes']['comment-tag']).each(function(){
				($src.html()==BlogIt.fn.xl('All') ?BlogIt.fn.commentAdminCheckbox(this, 'show', true) :BlogIt.fn.commentAdminCheckbox(this, 'hide', false) );
			});
			$src.html( $src.html()==BlogIt.fn.xl('All') ?BlogIt.fn.xl('None'): BlogIt.fn.xl('All') );
		},
		commentAdminCheckbox: function(src, action, opt){  //src [flip|show|hide]
			if (action == 'flip'){
				$('input[name="bi_CommentID[]"]', src).prop("checked", function(){ return !$(this).prop("checked"); });
			}else if (action == 'show'){
				if ( !$('input[name="bi_CommentID[]"]', src).length )
					$('.blogit-admin-links .blogit-admin-link:last', src).after('<input type="checkbox" name="bi_CommentID[]" value="'+ $(src).attr('id')+ '">');
				if (opt)  $('input[name="bi_CommentID[]"]', src).prop('checked',true);
			}else
				$('input[name="bi_CommentID[]"]'+ (opt ?':not(:checked)' :''), src).remove();
		},
		createURL: function(e, action){  //returns rowcount, url with serialilzed bi_CommentID[], and e jquery object set (1 or more)
			if (e.target.href){  //e is an event from a link click
				e.preventDefault();
				var rc = 1, url = e.target.href;
				e=$(e.target);
			}else{  //clicked admin menu, create url based on checkboxes
				var src = getCommentBlock();  //get comment block for title admin is on
				var rc = $('input[name="bi_CommentID[]"]', src).length;
				if (rc>0){
					var actionLink='.bi-link-comment-'+ action+ (action=='approve'||action=='unapprove' ?'d' :'')+ ':first';
					if (action=='approve'||action=='unapprove')
						actionLink += (',.bi-link-comment-'+ (action=='approve' ?'unapproved': 'approved')+ ':first');  //search for either approved OR unapproved link
					//:checked in case cursor is hovering; slice otherwise might have both approved and unapproved links
					var url = $(actionLink, src).slice( 0, 1 ).attr('href')+ '&'+ $('[name="bi_CommentID[]"]:checked', src).serialize();
					if (action=='approve'||action=='unapprove')
						url=url.replace(/action=bi_c(a|ua)/,(action=='approve' ?'action=bi_ca': 'action=bi_cua'));
					console.log(action+ ': '+ url);
					//find admin links on the row corresponding to action
					e = $('[name="bi_CommentID[]"]:checked', src).closest(BlogIt.pm['skin-classes']['comment-tag']+BlogIt.pm['skin-classes']['comment']).find(actionLink);
				}
			}
			console.log('URL Element');
			console.log(e);
			return {rc:rc, url:url, e:e};
		},
		adminAction: function(e, action){
			url=BlogIt.fn.createURL(e, action);
			if (url.rc>0){
				if (action=='delete'){
					dialogShow(BlogIt.fn.xl('Are you sure you want to delete ')+ url.rc+ BlogIt.fn.xl(' row'+ (url.rc>1 ?'s' :'')+ '?'), 'Yes', 'No', 300, {
						url: url.url,
						success:function(data){ objectRemove(url.e, data); }
					});
				}else if (action=='block'){
					BlogIt.fn.ajax({  //perform ajax call on block link, which retrieves the IP
						url: url.url,
						success: function(data){
							if (data.ip)  //success returns IP
								dialogShow(
									BlogIt.fn.xl('Enter the IP to block:')+ '<textarea id="blogit_ip" type="text">'+ data.ip+ '</textarea>', 'Submit', 'Cancel', 200, {
										url: function(){ return url.url+ encodeURI( '&bi_ip='+ $("#blogit_ip").val().replace(/\n/g,',') ); },
										success: function(data){ BlogIt.fn.showMsg(data); }
									});
						}
					});
				}else{
					BlogIt.fn.ajax({
						url: url.url,
						success: function(data){ if (data.result=='error')  BlogIt.fn.showMsg(data); flipCommentStatus(url.e, action); }
					});
				}
			}
		},
		//ajax editing opens a dialog with content from PmWiki, calls addValidation(), and then on submit calls ajaxSubmit(), which calls updateBlog/updateComment
		showEdit: function(e){
			e.preventDefault();
			BlogIt.fn.ajax({
				url:e.currentTarget.href,  //get the comment form from pmwiki; not .target, because actual target might be an image wrapped in an anchor
				success: function(data){
					if (data.result=='success'){  //form content returned in data.out
						var blog=$(e.currentTarget).is('.bi-link-blog-edit,.bi-link-blog-new');  //are we doing some blog related action?
						console.log('blog action: '+blog);
						dialog = new jBox('Confirm', {  //uses gloabal dialog var
							title: '&nbsp',  //make the title bar appear just for visual
							content: (blog ?$(data.out).filter('#wikiedit') :$(data.out)),  //remove the pmwiki editting help text
							_onOpen: function() {  //Override jbox default. Only change is to prevent dialog closing post confirm() so we manually close if form validates.
								this.submitButton.off('click.jBox-Confirm' + this.id).on('click.jBox-Confirm' + this.id, function() { this.options.confirm ? this.options.confirm() : eval(this.source.data('jBox-Confirm-submit')); }.bind(this));
							},
							closeButton: 'title',
							confirm: function (ev) { $('.jBox-content form').submit(); },  //will call ajaxSubmit(), set by call to addValidation below
							confirmButton: BlogIt.fn.xl('Submit'),
							cancelButton: BlogIt.fn.xl('Cancel'),
							onCloseComplete: function () { this.destroy(); },
							width: (blog?750:430), minWidth: (blog?750:430), maxWidth: 10000  //needed to override jbox default
						}).open();
						BlogIt.fn.addAutocomplete();
						BlogIt.fn.addValidation(e);  //adds submit handler for button in dialog
					}else  BlogIt.fn.showMsg(data || {msg:'Error on edit return.',result:'error'});
				}
			});
		},
		addValidation: function(e){
			$(BlogIt.pm['skin-classes']['blog-form']+ ' form').each(function(){
				$(this).validate({
					submitHandler: function(form) {  //Only if the form validates
						if ($(form).parents('.jBox-content').length){
							ajaxSubmit($(form), updateBlog, e);
						}else{
							$(window).off('beforeunload');
							form.submit();
						}
					},
					//TODO: Only require when class class="blogit-required""
					rules: {
						ptv_entrydate: {datetime: true},
						ptv_entryurl: {require_from_group: [1, 'input[name="ptv_entrytitle"],input[name="ptv_entryurl"]']},
						ptv_entrytitle: {require_from_group: [1, 'input[name="ptv_entrytitle"],input[name="ptv_entryurl"]']}
					}
				});
			});

			//dialog comment form is not wrapped in class
			$('.jBox-content form,'+ BlogIt.pm['skin-classes']['comment-list-wrapper']+ '+form').each(function(){
				$(this).validate({
					submitHandler: function(form) {
						//populate ptv_blogit_basepage in comments, as a pointer back to parent page
						if ( /action=bi_ce/.test(e.target.href) ){  //cr is handled in bi_HandleProcessForm()
							$m = e.target.href.match(/bi_base=(.*\..*)&/);
							if ($m)  $('[name="ptv_blogit_basepage"][value=""]', form).val($m[1]);
						}
						ajaxSubmit($(form), updateComment, e);  //mode is undefined when normal comment add, since no onclick handler defined
					},
					//TODO: Only require when class class="blogit-required""
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
		//DIV tags to peprform basic sanitize -- DIV tags are not returned, just the XL string.
		xl: function(t){ return ( (BlogIt.xl[t] ?$('<div>'+BlogIt.xl[t]+'</div>').html() :t) ); },
		ajax: function(ajax){  //wrapper to enable url to be a function
			ajax['url'] = ( typeof ajax.url == 'function' ?ajax.url() :ajax.url );  //either eval the fn, or use .url
			$.ajax(ajax);
		}
	};
}(jQuery);

