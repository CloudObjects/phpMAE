var phpMAE = {
    classPath : "",

    saveChanges : function(successCallback) {
        $('#source-submit, #rc-save-and-submit').attr('disabled', true);
        $('#source-error').hide();
        $.ajax({
            url : '/uploads/' + phpMAE.classPath + "/source.php",
            contentType : 'text/plain',
            processData : false,
            data : $('#source-code').val(),
            method : 'PUT'
        }).done(function() {
            Materialize.toast('Uploaded successfully.', 1000);
            if (typeof(successCallback) == "function")
                successCallback();
        }).fail(function(jqXHR) {
            Materialize.toast('Error during upload.', 1000);
            $('#source-submit, #rc-save-and-submit').attr('disabled', false);

            if (jqXHR.status == 400 && jqXHR.responseJSON
                    && jqXHR.responseJSON.error_message) {
                $('#source-error').show();
                $('#source-error div').text(jqXHR.responseJSON.error_message);
            }
        });
    },

    sendRequest : function() {
        $.ajax({
            url : '/run/' + phpMAE.classPath + $('#rc-path').val(),
            method : $('#rc-verb').val()
        }).done(function(data, statusText, jqXHR) {
            phpMAE.parseAndDisplayResponse(jqXHR);            
        }).fail(function(jqXHR) {
            phpMAE.parseAndDisplayResponse(jqXHR);
        });
    },

    parseAndDisplayResponse : function(jqXHR) {
        // Body
        $('#rc-response-body').html("<p>" + jqXHR.responseText + "</p>");

        // Headers
        var headers = jqXHR.getAllResponseHeaders().split("\n");
        var html = "";
        for (var l in headers) {
            var header = headers[l].split(":");
            if (header.length < 2) continue;
            html += "<tr><td>" + header[0].trim() + "</td><td>" + header[1].trim() + "</td></tr>";
        }
        $('#rc-response-headers').html("<table><tbody>" + html + "</tbody></table>");

        // Raw
        $('#rc-response-raw').html("<pre>"
            + jqXHR.status + " " + jqXHR.statusText + "\n"
            + jqXHR.getAllResponseHeaders() + "\n"
            + jqXHR.responseText
            + "</pre>");
    }
};

$(document).ready(function() {
    $('select').material_select();
    
    // Save changes in source code
    $('#source-submit').on('click', function() {
        phpMAE.saveChanges();
    });

    // Reenable save button on changes
    $('#source-code').on('change keyup', function() {
        $('#source-submit, #rc-save-and-submit').attr('disabled', false);
    });

    // Send a request to uploaded class
    $('#rc-submit').on('click', function() {
       phpMAE.sendRequest(); 
    });

    // Save changes, then make request
    $('#rc-save-and-submit').on('click', function() {
        phpMAE.saveChanges(function() {
            phpMAE.sendRequest();
        })
    });
});