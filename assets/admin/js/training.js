jQuery(document).ready(function($) {
    // Check if user previously closed the card
    if (localStorage.getItem('hezarfen_swimming_card_closed') === 'true') {
        return; // Don't show the card
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
            <div class="hezarfen-close-card" title="Close">×</div>
        </div>
    `;
    
    // Append to body for true screen positioning
    $('body').append(cardHTML);
    
    // Handle close button click
    $(document).on('click', '.hezarfen-close-card', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const card = $('.hezarfen-swimming-card');
        
        // Animate out
        card.css({
            'animation': 'swimOut 0.5s ease-in forwards',
            'pointer-events': 'none'
        });
        
        // Remove after animation
        setTimeout(function() {
            card.remove();
        }, 500);
        
        // Store in localStorage to remember user closed it
        localStorage.setItem('hezarfen_swimming_card_closed', 'true');
    });
    
    // Add swim out animation to CSS dynamically
    const style = document.createElement('style');
    style.textContent = `
        @keyframes swimOut {
            0% {
                transform: translateX(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateX(300px) rotate(10deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});