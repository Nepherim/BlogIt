jQuery(function($) {
	$("<div/>").attr({id:"dialog"}).appendTo("body");
	var target = $('.wikimessage');
	if (target.length){
		$('html,body').animate({scrollTop: target.offset().top-50}, 700);
	}

	$("form").validity(function() {
		$("#comment-author").require();
		$("#comment-email").require().match("email");
		$("#comment-website").match("url");
	});
//$.validity.setup({ outputMode:"modal" });
	$("#wikiedit form input[value='blogit-entry'][name='target']").parent('form').validity(function() {
		$("#entrydate").match("date");
		$("#entrytitle,#entryurl").assert(($("#entryurl").val() || $("#entrytitle").val()), "Either enter a Blog Title or a Pagename");
	});

	$("a[href*=blogitapprove],a[href*=blogitunapprove]").click(function(e){
		e.preventDefault();
		BlogIt.fn.ajax({ success: function(){ BlogIt.fn.commentStatus(e.target); } }, e);
	});

	$("a[href*=blogitcommentdelete]").click( function(e){
		BlogIt.fn.deleteDialog(e);
	});
	$("a[href*=action\\=bi_de]").click( function(e){
		BlogIt.fn.deleteDialog(e);
	});

	$("a[href*=action\\=bi_bip]").click( function(e){
		BlogIt.fn.commentBlock(e);
	});

});

BlogIt.fn = function(){
	//private declarations
	var unapprove;
	function updateCount(e,m){
		return e.replace(': '+m, ': '+(unapprove ?(parseInt(m)+1) :(m-1)));
	}
	function getEnteredIP(e){
		return e+'&bi_ip='+$("#blogit_ip").val();
	}

	//public functions
	return {
		xl: function(t){
			return (BlogIt.xl[t] || t);
		},
		ajax: function(ajax, e){
			ajax["dataType"] = ajax.dataType || "json";
			ajax["url"] = ( typeof ajax.url == "function" ?ajax.url(e.target.href) :(ajax.url || e.target.href) ) + '&bi_mode=ajax';
			ajax["context"] = ajax.context || e.target;
			$.ajax(ajax);
		},
		dialogClose: function(){
			$("#dialog").dialog("close").empty();
		},
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
				{success:function(){ BlogIt.fn.objectRemove(e.target); }},e);
		},
		objectRemove: function(o){
			$($(o).parents('li,tr')[0]).fadeOut(500, function(){ $(this).remove(); });
		},
		commentBlock: function(e){
			e.preventDefault();
			BlogIt.fn.ajax({
				success: function(data){
					if (data.ip){
						BlogIt.fn.dialogShow(
							"Commenter IP: "+data.ip+"<br/>Enter the IP to block:"+
							'<input id="blogit_ip" type="text" value="'+data.ip+'"/>','Submit','Cancel',
							{ url: function(e){ return getEnteredIP(e); },
								success: function(data){
									console.log(data);
								}
							}, e);
					}
				}
			},e);
		},
		commentStatus: function(o){
			$o = $($(o).parents('li')[0]);
			var bg = $o.css("backgroundColor");
			$o.css({backgroundColor:'#F0FED6'}).fadeTo(500, 0.2, function () {
				$(this).fadeTo("fast",1).css("background-color", bg);
			});
			unapprove = $(o).html()==BlogIt.fn.xl("unapprove");
			if (unapprove){
				o.href = o.href.replace("blogitunapprove", "blogitapprove");
				$(o).html(BlogIt.fn.xl("approve"));
			}else{
				o.href = o.href.replace("blogitapprove", "blogitunapprove");
				$(o).html(BlogIt.fn.xl("unapprove"));
			}
			var cc_Obj = $('a[href*=action=blogitadmin&s=unapproved-comments]');
			var cc_Txt = cc_Obj.html();
			cc_Obj.html( cc_Txt.replace(new RegExp(BlogIt.fn.xl('Unapproved Comments:')+' (\\d*)'), updateCount) );
		}
	}
}();
