jQuery(function($) {
	var target = $('.wikimessage');
	if (target.length){
		$('html,body').animate({scrollTop: target.offset().top-50}, 700);
	}

	$("#commentblock form").validate({
		rules: {
		  ptv_commentauthor: "required",
		  ptv_email: {required: true, email: true}
		}
	});
	$("#wikiedit form[action*=BlogIt-NewEntry]").validate({
		rules: {
		  ptv_entrytitle: "required"
		}
	});

	$("a[href*=blogitapprove],a[href*=blogitunapprove]").click(function(e){
		e.preventDefault();
		BlogIt.fn.ajax(e.target,BlogIt.fn.commentStatus);
	});

	$("a[href*=blogitcommentdelete]").click( function(e){
		BlogIt.fn.dialogDelete(e,BlogIt.fn.commentRemove)
	});
	$("a[href*=action=bi_de]").click( function(e){
		BlogIt.fn.dialogDelete(e,BlogIt.fn.blogRemove)
	});

});

BlogIt.fn = function(){
	//private declarations
	var unapprove;
	function updateCount(e,m){
		return e.replace(': '+m, ': '+(unapprove ?(parseInt(m)+1) :(m-1)));
	}

	//public functions
	return {
		xl: function(t){
			return (BlogIt.xl[t] || t);
		},
		dialogDelete: function(e,fn){
			e.preventDefault();
			$("<div/>", {
			  id: "dialog",
			  text: BlogIt.fn.xl("Are you sure you want to delete?"),
			}).appendTo("body");
			$("#dialog").dialog({
				resizable: false,
				modal: true,
				overlay: {
					backgroundColor: 'red',
					opacity: 0.5
				},
				autoOpen: false
			});
			BlogIt.fn.showDialog(e,fn);
		},
		showDialog: function(e,fn){
			var btn={};
			btn[BlogIt.fn.xl("Yes")] = function(){
				$(this).dialog("close");
				BlogIt.fn.ajax(e.target,fn);
			};
			btn[BlogIt.fn.xl("No")] = function() { $(this).dialog("close"); };

			$("#dialog")
				.dialog('option', 'buttons', btn)
				.dialog("open");
		},
		ajax: function(o,fn){
			$.ajax({ url: o.href+'&bi_mode=ajax',
				context: o,
				success: function(){ fn(o); }
			});
		},
		blogRemove: function(o){
			$($(o).parents('tr')[0]).fadeOut(500, function(){ $(this).remove(); });
		},
		commentRemove: function(o){
			$($(o).parents('li')[0]).fadeOut(500, function(){ $(this).remove(); });
		},
		commentStatus: function(o){
			var bg = $(o).parents('li').css("backgroundColor");
			$(o).parents('li').css({backgroundColor:'#F4A83D'}).fadeTo(500, 0.2, function () {
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

