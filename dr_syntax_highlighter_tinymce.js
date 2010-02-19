
(function() {	
	tinymce.create ('tinymce.plugins.dr_syntax_highlighter', {
		init : function (dr, url) {
			// Register executed command
			dr.addCommand ('dr_syntax_val', function() {
				dr.focusNode = tinyMCE.activeEditor.selection.getNode();
				var editorContent = tinyMCE.activeEditor.getContent ({format : 'text'});
				var selection = tinyMCE.activeEditor.selection.getContent({format : 'text'});

				dr.dr_syntax_values = dr_highlighter_Is_In_SyntaxBlock (dr, editorContent, tinyMCE.activeEditor);

				if ((!selection || selection == "") && !dr.dr_syntax_values) {
					alert ('Please select some source code first!');
					return false;
				}
				dr.windowManager.open({ 
					file : url + '/dr_highlighter_options_tinymce.htm', 
					width : 300, 
					height : 300, 
					inline : 1 
				}, { 
					plugin_url : url,
					selection : selection
				}); 
				
				return false;
			});

			// Register TinyMCE image button
			dr.addButton ('dr_syntax_highlighter', {title : 'Dottoro Highlight', cmd : 'dr_syntax_val', image:url + '/images/dr_highlighter_tinymce.png'});
		},

		getInfo : function() {
			return {
				longname :	'Dottoro Syntax Highlighter',
				author :	'dottoro.com',
				authorurl :	'http://tools.dottoro.com/services/highlighter',
				infourl :	'http://tools.dottoro.com/services/highlighter',
				version :	tinymce.majorVersion + "." + tinymce.minorVersion
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add ('dr_syntax_highlighter', tinymce.plugins.dr_syntax_highlighter);
})();

function dr_highlighter_Is_In_SyntaxBlock (dr, editorContent, activeEditor) {
	if (editorContent) {
		var focusNode = dr.focusNode;
		var attr = focusNode.getAttribute ("drsyntax");
		if (attr) {
			return [focusNode, attr];
		}
		var paren = focusNode.parentNode;
		while (paren && paren != activeEditor) {
			attr = focusNode.getAttribute ("drsyntax");
			if (attr) {
				return [focusNode, attr];
			}
			paren = paren.parentNode;
		}
	}
	return false;
}
