jQuery(document).ready(function($){

    $('#wc_hezarfen_billing_district').select2();
    $('#wc_hezarfen_billing_neighborhood').select2();

});


jQuery( function( $ ) {

    $("#billing_state").on("select2:select", function(e){


        // empty district select box
        $('#wc_hezarfen_billing_district').empty();


        // push placeholder data
        $('#wc_hezarfen_billing_district')
            .append($("<option></option>")
                .attr("value", "")
                .text("Lütfen seçiniz"));


        // get selected data
        var selected = e.params.data;



        var data = {

            'action':'wc_hezarfen_get_districts',
            'city_name':selected.text

        };

        jQuery.post(wc_hezarfen_ajax_object.ajax_url, data, function(response){

            var districts = JSON.parse(response);

            $.each(districts, function (district_id, district_name) {

                $('#wc_hezarfen_billing_district')
                    .append($("<option></option>")
                        .attr("value", district_id)
                        .text(district_name));

            });

        });

    });



    $("#wc_hezarfen_billing_district").on("select2:select", function(e){


        // empty neighborhood select box
        $('#wc_hezarfen_billing_neighborhood').empty();


        // push placeholder data
        $('#wc_hezarfen_billing_neighborhood')
            .append($("<option></option>")
                .attr("value", "")
                .text("Lütfen seçiniz"));


        // get selected data
        var selected = e.params.data;


        var data = {

            'action':'wc_hezarfen_get_neighborhoods',
            'district_id':selected.id

        };

        jQuery.post(wc_hezarfen_ajax_object.ajax_url, data, function(response){

            var neighborhoods = JSON.parse(response);

            $.each(neighborhoods, function (neighborhood_id, neighborhood_name) {

                $('#wc_hezarfen_billing_neighborhood')
                    .append($("<option></option>")
                        .attr("value", neighborhood_id)
                        .text(neighborhood_name));

            });

        });

    });

} );