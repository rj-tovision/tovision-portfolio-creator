document.addEventListener('DOMContentLoaded', function() {
    var lightbox = document.getElementById('portfolio-lightbox');
    var lightboxImg = document.querySelector('.lightbox-image');
    var links = document.querySelectorAll('.portfolio-lightbox-link');
    var closeBtn = document.querySelector('.lightbox-close');
    var prevBtn = document.querySelector('.lightbox-prev');
    var nextBtn = document.querySelector('.lightbox-next');
    
    var currentIndex = 0;
    var images = [];

    // Collect all image URLs
    links.forEach(function(link, index) {
        images.push(link.getAttribute('href'));
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            openLightbox(index);
        });
    });

    function openLightbox(index) {
        currentIndex = index;
        lightbox.style.display = "block";
        lightboxImg.src = images[currentIndex];
        document.body.style.overflow = 'hidden'; // Disable scroll
    }

    function closeLightbox() {
        lightbox.style.display = "none";
        document.body.style.overflow = ''; // Enable scroll
    }

    function showNext() {
        currentIndex = (currentIndex + 1) % images.length;
        lightboxImg.src = images[currentIndex];
    }

    function showPrev() {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        lightboxImg.src = images[currentIndex];
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', showNext);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', showPrev);
    }

    // Close on outside click
    window.addEventListener('click', function(e) {
        if (e.target == lightbox) {
            closeLightbox();
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (lightbox.style.display === "block") {
            if (e.key === "Escape") closeLightbox();
            if (e.key === "ArrowRight") showNext();
            if (e.key === "ArrowLeft") showPrev();
        }
    });
});