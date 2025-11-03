document.addEventListener('DOMContentLoaded', function () {
    const slides = document.querySelector('.carousel-slides');
    if (!slides) return;

    const slideItems = document.querySelectorAll('.slide');
    const prevBtn = document.querySelector('.prev');
    const nextBtn = document.querySelector('.next');
    let currentIndex = 0;

    function updateCarousel() {
        const slideWidth = slideItems[0].clientWidth;
        slides.style.transform = `translateX(${-currentIndex * slideWidth}px)`;
    }

    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % slideItems.length;
        updateCarousel();
    });

    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + slideItems.length) % slideItems.length;
        updateCarousel();
    });

    window.addEventListener('resize', updateCarousel);
});