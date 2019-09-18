var sessionId = null;
var codeEditor = CodeMirror(document.getElementById('phpmae-source-editor'), {
    value: "<?php\n\nclass MyPhpMAEClass {\n\n    public function hello($name) {\n        return \"Hello \".$name.\"!\";\n    }\n\n}",
    lineNumbers: false,
    matchBrackets: true,
    mode: "application/x-httpd-php",
    indentUnit: 4,
    indentWithTabs: false
});

function execute(form) {
    var request = {
        sourceCode : codeEditor.getValue(),
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
        });
    });
}