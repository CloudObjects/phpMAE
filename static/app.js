$(function() {
    var sessionId = sessionStorage.getItem('phpMaeSessionId');
    var functionMatcher = /(?:\/\*\*((?:[\s\S](?!\/\*))*?)\*\/+\s*)?public\s+function\s+(\w+)\s*\((.*)\)/g;
    var codeEditor = CodeMirror(document.getElementById('phpmae-source-editor'), {
        lineNumbers: false,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 4,
        indentWithTabs: false
    });
    var configEditor = CodeMirror(document.getElementById('phpmae-config-editor'), {
        lineNumbers: false,
        matchBrackets: true,
        mode: "application/xml",
        indentUnit: 4,
        indentWithTabs: false
    });
    var dirty = false;

    function checkSignInStatus() {
        if (typeof(COWebApp) !== "undefined" && COWebApp.getAccountContext() !== null) {
            // Signed in
            $('.phpmae-signedout-only').hide();
            $('.phpmae-signedin-only').show();
        } else {
            // Signed out
            $('.phpmae-signedin-only').hide();
            $('.phpmae-signedout-only').show();            
        }
    }

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
        loadTemplate : function(template) {
            if (dirty == false || window.confirm("Do you want to load this template? This will delete your code changes!"))
                fetch('/templates/' + template + '.txt').then(function (response) {
                    response.text().then(function(data) {
                        var template = data.split("\n----\n");
                        if (template.length !== 2) {
                            alert("Invalid template file!");
                            return;
                        }
                        codeEditor.setValue(template[0]);
                        configEditor.setValue(template[1]);                        
                    
                        // Add class name
                        var classDefinition = template[0].match(/class\s+(\w+)/);
                        $('input[name="class"]').val(
                            (classDefinition.length > 1 ? classDefinition[1] : "")
                        );

                        // Build executor function list                    
                        var functionOptionsHtml = "";
                        var firstFunction = null;
                        do {
                            functionMatch = functionMatcher.exec(template[0]);
                            if (functionMatch && functionMatch[2] != "__construct") {
                                firstFunction = firstFunction || functionMatch[2];
                                functionOptionsHtml += '<option value="' + functionMatch[2] + '">'
                                    + functionMatch[2] + '()</option>';
                            }
                        } while (functionMatch);
                        $('select[name=function]').html(functionOptionsHtml).formSelect();
                        updateParameters(firstFunction);

                        // Clear output
                        $('#phpmae-result').text('');
                        
                        dirty = false;                        
                    });
                });
        },
        signin : function() {
            alert("Coming soon!")
        }
    };

    codeEditor.on("change", function(instance, change) {
        dirty = true;
        var line = instance.getLine(change.from.line);
        if (line.indexOf("class") > -1) {
            var classDefinition = instance.getValue().match(/class\s+(\w+)/);
            $('input[name="class"]').val(
                (classDefinition.length > 1 ? classDefinition[1] : "")
            );
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

    configEditor.on("change", function(instance, change) {
        dirty = true;
    });

    $('.tabs').tabs({
        onShow : function() {
            codeEditor.refresh();
            configEditor.refresh();
        }
    });

    $('select[name=function]').on('change', function() {
        updateParameters($(this).val());
    }).formSelect();

    $('.dropdown-trigger').dropdown({
        constrainWidth : false,
        coverTrigger : false
    });

    function fixHeight() {
        $('.CodeMirror').css('height', ($(window).height() - $('.CodeMirror').offset().top - 30) + 'px');
    }    

    $(window).on('resize', fixHeight);
    fixHeight();
    checkSignInStatus();
    phpMAE.loadTemplate('helloworld');
});