import { initFlowbite } from 'flowbite';
import './style.css';

function expandShippingCompanies() {
    const content = document.getElementById('shipping-companies');
    if (content.classList.contains('max-h-24')) {
        content.classList.remove('max-h-24');
        content.classList.add('max-h-[1000px]');
    } else {
        content.classList.remove('max-h-[1000px]');
        content.classList.add('max-h-24');
    }
}

jQuery(document).ready(($)=>{
    $('.hez-ui .h-expand').on('click', () => {
        expandShippingCompanies();
    });
});
