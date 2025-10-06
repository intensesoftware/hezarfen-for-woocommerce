jQuery(document).ready(function($) {
    // Only show on Hezarfen settings page
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page');
    const tab = urlParams.get('tab');
    
    if (page !== 'wc-settings' || tab !== 'hezarfen') {
        return; // Not on Hezarfen settings page
    }
    
    // Create and inject the swimming card directly into body
    const cardHTML = `
        <div class="hezarfen-swimming-card">
            <div class="hezarfen-card-content">
                <div class="hezarfen-youtube-icon">
                    <svg width="20" height="14" viewBox="0 0 24 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M23.498 2.64C23.217 1.64 22.438 0.861 21.438 0.58C19.578 0.061 12 0.061 12 0.061C12 0.061 4.422 0.061 2.562 0.58C1.562 0.861 0.783 1.64 0.502 2.64C-0.017 4.5 -0.017 8.371 -0.017 8.371C-0.017 8.371 -0.017 12.242 0.502 14.102C0.783 15.102 1.562 15.881 2.562 16.162C4.422 16.681 12 16.681 12 16.681C12 16.681 19.578 16.681 21.438 16.162C22.438 15.881 23.217 15.102 23.498 14.102C24.017 12.242 24.017 8.371 24.017 8.371C24.017 8.371 24.017 4.5 23.498 2.64Z" fill="#FF0000"/>
                        <path d="M9.545 12.011L15.818 8.371L9.545 4.731V12.011Z" fill="white"/>
                    </svg>
                </div>
                <div class="hezarfen-card-text">
                    <strong>Abone olun</strong>
                    <span>eğitimler için!</span>
                </div>
            </div>
            <a href="https://www.youtube.com/@hezarfenforwoocommerce" target="_blank" rel="noopener noreferrer" class="hezarfen-card-link" aria-label="Subscribe to Hezarfen YouTube Channel"></a>
        </div>
    `;
    
    // Append to body for true screen positioning
    $('body').append(cardHTML);
    
    // Create and inject WhatsApp support card
    const whatsappCardHTML = `
        <div class="hezarfen-whatsapp-card">
            <div class="hezarfen-card-content">
                <div class="hezarfen-whatsapp-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 0C5.373 0 0 5.373 0 12C0 14.251 0.655 16.346 1.789 18.118L0.621 22.498C0.535 22.817 0.743 23.136 1.062 23.222C1.146 23.246 1.234 23.253 1.321 23.242L5.882 22.211C7.654 23.345 9.749 24 12 24C18.627 24 24 18.627 24 12C24 5.373 18.627 0 12 0Z" fill="#25D366"/>
                        <path d="M17.472 14.382C17.158 14.224 15.618 13.467 15.328 13.358C15.039 13.25 14.827 13.196 14.614 13.511C14.402 13.825 13.797 14.541 13.609 14.753C13.421 14.966 13.233 14.991 12.919 14.833C12.605 14.675 11.576 14.341 10.352 13.252C9.393 12.402 8.748 11.347 8.56 11.033C8.372 10.718 8.539 10.544 8.698 10.386C8.84 10.245 9.012 10.018 9.17 9.83C9.328 9.642 9.382 9.503 9.491 9.291C9.599 9.078 9.545 8.89 9.466 8.732C9.387 8.574 8.748 7.032 8.483 6.403C8.226 5.791 7.964 5.871 7.768 5.862C7.581 5.853 7.369 5.851 7.157 5.851C6.944 5.851 6.605 5.93 6.316 6.244C6.027 6.559 5.217 7.316 5.217 8.858C5.217 10.4 6.341 11.892 6.499 12.105C6.657 12.317 8.743 15.456 11.941 16.815C12.723 17.156 13.335 17.358 13.814 17.509C14.598 17.761 15.311 17.726 15.875 17.645C16.504 17.555 17.738 16.89 18.003 16.16C18.268 15.43 18.268 14.808 18.189 14.674C18.11 14.541 17.897 14.462 17.583 14.304L17.472 14.382Z" fill="white"/>
                    </svg>
                </div>
                <div class="hezarfen-card-text">
                    <strong>Ücretsiz Destek</strong>
                    <span>WhatsApp'tan yazın!</span>
                </div>
            </div>
            <a href="https://intense.com.tr/whatsapp-destek" target="_blank" rel="noopener noreferrer" class="hezarfen-card-link" aria-label="Get Free WhatsApp Support"></a>
        </div>
    `;
    
    // Append WhatsApp card to body
    $('body').append(whatsappCardHTML);
});