var sessionId = null;
var codeEditor = CodeMirror(document.getElementById('phpmae-source-editor'), {
    value: "<?php\n\nclass MyPhpMAEClass {\n\n    public function hello($name) {\n        return \"Hello \".$name.\"!\";\n    }\n\n}",
    lineNumbers: false,
    matchBrackets: true,
    mode: "application/x-httpd-php",
    indentUnit: 4,
    indentWithTabs: false
});
var functionMatcher = /(?:\/\*\*((?:[\s\S](?!\/\*))*?)\*\/+\s*)?public\s+function\s+(\w+)\s*\((.*)\)/g;

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
        document.getElementsByName('function')[0].innerHTML = functionOptionsHtml;
        updateParameters(document.getElementsByName('function')[0].value);
    }
});

function execute(form) {
    var request = {
        sourceCode : codeEditor.getValue(),
        class : form.querySelectorAll('[name=class]')[0].value,
        method : form.querySelectorAll('[name=function]')[0].value,
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
            document.getElementById('phpmae-result').innerText = data.content;
            if (data.status == "error")
                document.getElementById('phpmae-result').style.color = "#ff0000";
            else
                document.getElementById('phpmae-result').style.color = "#000000";            
        });
    });
}

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
                parametersHtml += '<div class="pure-control-group">'
                    + '<label for="' + parameter.substr(1) + '">' + parameter + '</label>'
                    + '<input name="' + parameter.substr(1) + '" type="text" value="" />'
                    + '</div>';
            }
        }        
    } while (functionMatch);
    document.getElementById('phpmae-parameter-container').innerHTML = parametersHtml;
}