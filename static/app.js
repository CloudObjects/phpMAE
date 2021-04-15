$(function() {
    var sessionId = sessionStorage.getItem('phpMaeSessionId');
    var functionMatcher = /(?:\/\*\*((?:[\s\S](?!\/\*))*?)\*\/+\s*)?public\s+function\s+(\w+)\s*\((.*)\)/g;
    var codeEditor = CodeMirror(document.getElementById('phpmae-source-editor'), {
        value: "<?php\n\nclass MyPhpMAEClass {\n\n    public function hello($name) {\n        return \"Hello \".$name.\"!\";\n    }\n\n}",
        lineNumbers: false,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 4,
        indentWithTabs: false
    });
    var configEditor = CodeMirror(document.getElementById('phpmae-config-editor'), {
        value: "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n     xmlns:phpmae=\"coid://phpmae.dev/\">\n\n    <phpmae:Class rdf:about=\"coid://playground.phpmae/MyPhpMAEClass\">\n    </phpmae:Class>\n\n</rdf:RDF>",
        lineNumbers: false,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 4,
        indentWithTabs: false
    })

    function updateParameters(functionName) {
        var sourceCode = codeEditor.getValue();
        var container = $('#phpmae-parameter-container');
        var parametersHtml = "";
            do {
            functionMatch = functionMatcher.exec(sourceCode);
            if (functionMatch && functionMatch[2] == functionName) {
                var parameters = functionMatch[3].split(",");
                for (var p in parameters) {
                    var parameter = parameters[p].trim();
                    if (parameter[0] != "$" || parameter.length < 2) continue;
                    var noPrefix = parameter.substr(1);
                    var oldValue = container.find('[name=' + noPrefix + ']').val();
                    parametersHtml += '<div class="input-field col s12">'
                        + '<label for="' + noPrefix + '">' + parameter + '</label>'
                        + '<input name="' + noPrefix+ '" type="text" value="'
                        + (typeof(oldValue) != 'undefined' ? oldValue : '') + '" />'
                        + '</div>';
                }
            }        
        } while (functionMatch);
        container.html(parametersHtml);
        M.updateTextFields();
    }

    window.phpMAE = {
        execute : function(form) {
            form = $(form);
            form.find('button').addClass('disabled');

            var request = {
                config : configEditor.getValue(),
                sourceCode : codeEditor.getValue(),
                class : form.find('[name=class]').val(),
                method : form.find('[name=function]').val(),
                params : {}
            };
            if (sessionId != null) request.session = sessionId;
            var parameters = form.find('input');
            for (var p in parameters)
                request.params[parameters[p].name] = parameters[p].value;
        
            fetch('/run', {
                method : 'POST',
                headers : { 'Content-Type' : 'application/json' },
                body : JSON.stringify(request)
            }).then(function(response) {                
                response.json().then(function(data) {
                    if (data.session != sessionId) {
                        sessionId = data.session;
                        sessionStorage.setItem('phpMaeSessionId', sessionId);
                    }
                    $('#phpmae-result')
                        .text((typeof(data.content) == "object")
                            ? JSON.stringify(data.content, null, 2)
                            : data.content)
                        .css('color', (data.status == "error") ? "#ff0000" : "#000000");
                    form.find('button').removeClass('disabled');
                });
            });
        },
        signin : function() {
            alert("Coming soon!")
        }
    };

    codeEditor.on("change", function(instance, change) {
        var line = instance.getLine(change.from.line);
        if (line.indexOf("class") > -1) {
            var classDefinition = instance.getValue().match(/class\s+(\w+)/);
            document.getElementsByName("class")[0].value = 
                (classDefinition.length > 1 ? classDefinition[1] : "");
        } else
        if (line.indexOf("function") > -1) {
            var sourceCode = instance.getValue();
            var selector = $('select[name=function]');
            var currentlySelected = selector.val();
            var previousExists = false;
            var functionOptionsHtml = "";
            do {
                functionMatch = functionMatcher.exec(sourceCode);
                if (functionMatch && functionMatch[2] != "__construct") {
                    previousExists = previousExists || (functionMatch[2] == currentlySelected);
                    functionOptionsHtml += '<option value="' + functionMatch[2] + '"'
                        + (functionMatch[2] == currentlySelected ? ' selected="selected"' : '')
                        + '>' + functionMatch[2] + '()</option>';
                }
            } while (functionMatch);
            selector.html(functionOptionsHtml).formSelect();
            updateParameters(selector.val());
        }
    });

    $('.tabs').tabs();
    $('select[name=function]').on('change', function() {
        updateParameters($(this).val());
    }).formSelect();
});