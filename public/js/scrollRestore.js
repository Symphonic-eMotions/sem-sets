/**
 * Scroll Restore Script
 * Onthoudt de scroll-positie bij het verzenden van een formulier
 * en herstelt deze na een herlaadbeurt (PRG pattern).
 */
document.addEventListener('DOMContentLoaded', function() {
    const STORAGE_KEY = 'scroll_pos_' + window.location.pathname;
    const form = document.getElementById('document-form');

    // 1. Herstel scroll positie als deze is opgeslagen
    const savedPos = sessionStorage.getItem(STORAGE_KEY);
    if (savedPos !== null) {
        // We gebruiken een kleine timeout om te zorgen dat alle dynamische content
        // (zoals de levels en tracks) al is gerenderd door de andere scripts.
        setTimeout(() => {
            window.scrollTo({
                top: parseInt(savedPos, 10),
                behavior: 'smooth'
            });
            // Verwijder na herstel om ongewenst scrollen bij handmatige refresh te voorkomen
            sessionStorage.removeItem(STORAGE_KEY);
        }, 150);
    }

    // 2. Sla positie op bij verzenden van het formulier
    if (form) {
        form.addEventListener('submit', function() {
            sessionStorage.setItem(STORAGE_KEY, window.scrollY);
        });
    }
});
