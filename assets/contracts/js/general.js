jQuery( document ).ready(function() {
    /** Ödeme metodunu tespit et, ve ilgili alanları güncelle */
    jQuery(document.body).on('change', 'input[name="payment_method"]', function() {
        var inputID = jQuery(this).attr('id');
        var label = jQuery("label[for='"+ inputID +"']").text();
        var paymentMethod = jQuery.trim(label);

        /** ödeme metodu alanını güncelle */
        jQuery(".obf_mss_payment_method").replaceWith('<span class="obf_mss_payment_method">'+ paymentMethod +'</span>');
    });
});