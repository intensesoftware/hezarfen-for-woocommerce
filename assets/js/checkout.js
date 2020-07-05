jQuery( function( $ ) {

    $("#billing_state").on("select2:select", function(e){

        var selected = e.params.data;

        var data = {

            'action':'wc_hezarfen_get_districts',
            'city_name':selected.text

        };

        jQuery.post(wc_hezarfen_ajax_object.ajax_url, data, function(response){

            var response_obj = JSON.parse(response);

            console.log(response_obj);

        });

    });

} );