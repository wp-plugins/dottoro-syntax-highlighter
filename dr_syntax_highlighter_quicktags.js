
//Add Dr Syntax Button
edButtons.push (new edButton('ed_drsyntax', 'Dr-Highlight', '', '', ''));

// On page load replace the onclick function
jQuery("#ed_drsyntax").ready(function($){
	$("#ed_drsyntax").removeAttr("onclick");
	var $dialog = $('<div style="background-color:#f1f1f1;"></div>')
		.html('<iframe id="dr_highlighter_option_frame" src="' + dr_highlighter_plugin_dir + 'dr_highlighter_options_quicktags.htm" style="width:290px; height:270px; border:none;" frameborder="no"></iframe>')
		.dialog({
			autoOpen: false,
			title: 'Dottoro Syntax Highlighter',
			modal: true,
			resizable: false,
			width: 310,
			height: 337
		});

	$("#ed_drsyntax").click(function() {
		var selArr = dr_highlighter_Get_Selection ();
		if (selArr[0] == selArr[1] && !selArr[3]) {
			alert ('Please select some source code first!');
		} else {
			dr_highlighter_Init_Dialog (selArr, $dialog);
			$dialog.dialog('open');
		}
	});
});

function dr_highlighter_Get_Selection () {
	var textarea = document.getElementById ("content");
	if (textarea.selectionStart !== undefined) {

		var	start = textarea.selectionStart;
		var	end = textarea.selectionEnd;
		var	selection = textarea.value.substring (start, end);
	}
	else if (document.selection) {
		textarea.focus();
		var range = document.selection.createRange ();

		var area_range = range.duplicate();
		area_range.moveToElementText( textarea );
		area_range.setEndPoint( 'EndToEnd', range );

		var start = area_range.text.length - range.text.length;
		var end = start + range.text.length;
		var	selection = range.text;
	}

	if (start != undefined) {
		var val = textarea.value;
		var beforeSelStart = val.substring (0,start);
		var syntaxBlockStart = beforeSelStart.lastIndexOf ('<pre drsyntax');
		if (syntaxBlockStart > -1) {
			if (beforeSelStart.indexOf ('</pre>', syntaxBlockStart) == -1) {
				var syntaxBlockEnd = val.indexOf ('"', syntaxBlockStart + 15);
				var settings = val.substring (syntaxBlockStart + 15, syntaxBlockEnd);
				return [start, end, selection, settings, syntaxBlockStart, syntaxBlockEnd];
			}
		} 
		return [start, end, selection];
	}
	else {
		return false;
	}
}

function dr_highlighter_Init_Dialog (selArr, $dialog) {
	var iframe = document.getElementById ("dr_highlighter_option_frame");
	iframe.contentWindow.Init (selArr, $dialog);
}
