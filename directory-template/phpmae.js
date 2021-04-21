$('.collapsible-body form').submit(function(event) {
    event.preventDefault();
    var form = $(this);
    form.find('.btn').addClass('disabled');
    
    var fields = form.serializeArray();
    var className = "";
    var method = "";
    var params = {};
    for (var f in fields) {
        switch (fields[f].name) {
            case "method":
                method = fields[f].value;
                break;
            case "class":
                className = fields[f].value;
                break;
            default:
                params[fields[f].name.substr(2)] = fields[f].value;
        }
    }

    axios.post('https://phpmae.cloudobjects.io/' + className, {
        jsonrpc : '2.0',
        id : 'COWebsite',
        method : method,
        params : params
    }).then(function(response) {
        form.find('.btn').removeClass('disabled');

        if (typeof(response.data) == "object" && response.data.hasOwnProperty('result')) {
            form.find('.console-response-status').text('Success');
            form.find('.console-response').text(
                typeof(response.data.result) == "string" ? response.data.result
                : JSON.stringify(response.data.result, null, 4));
        } else
        if (typeof(response.data) == "object" && !response.data.hasOwnProperty('error')) {
            form.find('.console-response-status').text('Success');
            form.find('.console-response').text(JSON.stringify(response.data, null, 4));
        } else
        if (typeof(response.data) == "object") { 
            form.find('.console-response-status').text('Error');
            form.find('.console-response').text(JSON.stringify(response.data.error, null, 4))
        } else {
            form.find('.console-response-status').text('Success');
            form.find('.console-response').text(response.data);
        }
    }).catch(function(error) {
        form.find('.btn').removeClass('disabled');

        form.find('.console-response-status').text('Exception');
        form.find('.console-response').text(JSON.stringify(error, null, 4))
    });
});