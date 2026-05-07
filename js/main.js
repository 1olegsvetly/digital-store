// Main JavaScript for frontend

document.addEventListener('DOMContentLoaded', function() {
    // Lazy loading images fallback
    const images = document.querySelectorAll('img[loading="lazy"]');
    
    // Add smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    // Filter form auto-submit on change (optional)
    const filterInputs = document.querySelectorAll('.filter-form input');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Could auto-submit here if desired
        });
    });
    
    // Image thumbnail click handler
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const mainImage = document.getElementById('main-image');
            if (mainImage) {
                mainImage.src = this.src;
            }
        });
    });
    
    console.log('Digital Store loaded successfully');
});
