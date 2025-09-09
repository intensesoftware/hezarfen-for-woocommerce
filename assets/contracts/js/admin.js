/* MSS Admin JavaScript */
jQuery(document).ready(function($) {
    // Handle contract template selection
    $('select[id*="mss_taslak_id"], select[id*="obf_taslak_id"]').on('change', function() {
        var $this = $(this);
        var selectedValue = $this.val();
        
        if (selectedValue) {
            $this.closest('tr').find('.description').hide();
        } else {
            $this.closest('tr').find('.description').show();
        }
    });
    
    // Initialize on page load
    $('select[id*="mss_taslak_id"], select[id*="obf_taslak_id"]').trigger('change');
});