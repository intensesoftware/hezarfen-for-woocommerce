import { initFlowbite } from 'flowbite';
import './style.css';

jQuery(document).ready(($)=>{
    $('.hez-ui .h-expand').click(function() {
        var $content = $('#shipping-companies');
        var $button = $(this);

        // Toggle max height class
        if ($content.hasClass('max-h-24')) {
          $content.removeClass('max-h-24').addClass('max-h-[1000px]');
          $button.text($button.data('show-less-label')); // Change button label to "Show less"
        } else {
          $content.removeClass('max-h-[1000px]').addClass('max-h-24');
          $button.text($button.data('show-more-label')); // Change button label to "Show more"
        }
      });
});
