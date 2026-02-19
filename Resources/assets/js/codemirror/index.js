"use strict";
import './index.scss'
import CodeMirror from "codemirror";
import "codemirror/mode/yaml/yaml";
import "codemirror/addon/edit/matchbrackets";
import "codemirror/addon/comment/continuecomment";
import "codemirror/addon/comment/comment";


function init() {
    $("textarea[data-code-mode]").each(function () {

        let mode = this.dataset.codeMode;
        let options = {
            indentWithTabs: false,
            smartIndent: true,
            indentUnit: 4,
            tabSize: 4,
            extraKeys: {
                Tab: function (cm) {
                    let spaces = " ".repeat(cm.getOption("indentUnit"));
                    cm.replaceSelection(spaces);
                }
            }
        };

        // if (mode === "application/json") {
        // 	options = $.extend({}, options, {
        // 		matchBrackets: true,
        // 		autoCloseBrackets: true,
        // 		lineWrapping: true
        // 	});
        // }


        options = $.extend({}, {
            lineNumbers: true,
            mode: mode,
            theme: "solarized light"
        }, options);


        CodeMirror.fromTextArea(this, options);
    });
}

$(init);