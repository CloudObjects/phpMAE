$(function() {
    var sessionId = null;
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
        value: "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n     xmlns:phpmae=\"coid://phpmae.cloudobjects.io/\">\n\n    <phpmae:Class rdf:about=\"coid://playground.phpmae/MyPhpMAEClass\">\n    </phpmae:Class>\n\n</rdf:RDF>",
        lineNumbers: false,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 4,
        indentWithTabs: false
    })

    function updateParameters(functionName) {
        var sourceCode = codeEditor.getValue();
        var parametersHtml = "";
        do {
            functionMatch = functionMatcher.exec(sourceCode);
            if (functionMatch && functionMatch[2] == functionName) {
                var parameters = functionMatch[3].split(",");
                for (var p in parameters) {
                    var parameter = parameters[p].trim();
                    if (parameter[0] != "$") continue;
                    parametersHtml += '<div class="input-field col s12">'
                        + '<label for="' + parameter.substr(1) + '">' + parameter + '</label>'
                        + '<input name="' + parameter.substr(1) + '" type="text" value="" />'
                        + '</div>';
                }
            }        
        } while (functionMatch);
        $('#phpmae-parameter-container').html(parametersHtml);
    }

    window.phpMAE = {
        execute : function(form) {
            var request = {
                config : configEditor.getValue(),
                sourceCode : codeEditor.getValue(),
                class : $(form).find('[name=class]').val(),
                method : $(form).find('[name=function]').val(),
                params : {}
            };
            if (sessionId != null) request.session = sessionId;
            var parameters = form.querySelectorAll('input');
            for (var p in parameters)
                request.params[parameters[p].name] = parameters[p].value;
        
            fetch('/run', {
                method : 'POST',
                headers : { 'Content-Type' : 'application/json' },
                body : JSON.stringify(request)
            }).then(function(response) {
                response.json().then(function(data) {
                    sessionId = data.session;
                    $('#phpmae-result')
                        .text(data.content)
                        .css('color', (data.status == "error") ? "#ff0000" : "#000000");
                });
            });
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
            var functionOptionsHtml = "";
            do {
                functionMatch = functionMatcher.exec(sourceCode);
                if (functionMatch)
                    functionOptionsHtml += '<option value="' + functionMatch[2] + '">'
                        + functionMatch[2] + '()</option>';
            } while (functionMatch);
            $('select[name=function]').html(functionOptionsHtml).formSelect();
            updateParameters($('select[name=function]').val());
        }
    });

    $('.tabs').tabs();
    $('select[name=function]').on('change', function() {
        updateParameters($(this).val());
    }).formSelect();
});