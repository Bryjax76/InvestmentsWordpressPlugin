document.addEventListener('DOMContentLoaded', function () {
    const menuBar = document.querySelector('.single-page-menu-bar .navigation');
    if (!menuBar) return;

    // Wyczyść istniejące linki
    menuBar.innerHTML = '';

    // Znajdź wszystkie sekcje z atrybutem data-hotlink
    const sections = document.querySelectorAll('section[data-hotlink][data-hotlink-name]');
    const menuLinks = [];

    sections.forEach(section => {
        const hotlink = section.dataset.hotlink;
        const name = section.dataset.hotlinkName;

        // Usuń # z początku jeśli istnieje dla ID
        const id = hotlink.replace(/^#/, '');

        // Ustaw ID sekcji jeśli nie istnieje
        if (!section.id) {
            section.id = id;
        }

        // Dodaj link do menu
        const link = document.createElement('a');
        link.href = hotlink;
        link.textContent = name;
        menuBar.appendChild(link);
        
        // Zapisz link i sekcję do tablicy
        menuLinks.push({
            link: link,
            section: section,
            id: id
        });
    });

    // PŁYNNE PRZEWIJANIE Z OFFSETEM DLA FIXED HEADER
    const headerHeight = document.querySelector('.site-header')?.offsetHeight || 130;
    const menuBarHeight = document.querySelector('.single-page-menu-bar')?.offsetHeight || 50;
    const offset = headerHeight + menuBarHeight + 20;

    // Przechwytuj kliknięcia w linki menu
    document.querySelectorAll('.single-page-menu-bar a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();

            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });

                history.pushState(null, null, targetId);
                
                // Dodaj klasę active do klikniętego linku
                menuLinks.forEach(item => {
                    if (item.link === this) {
                        item.link.classList.add('active');
                    } else {
                        item.link.classList.remove('active');
                    }
                });
            }
        });
    });

    // FUNKCJA DO AKTUALIZACJI AKTYWNEGO LINKU NA PODSTAWIE SCROLLA
    function updateActiveLink() {
        const scrollPosition = window.scrollY + offset + 5; // +5 dla lepszego wykrywania
        
        let activeFound = false;
        
        // Przejdź przez wszystkie sekcje od ostatniej do pierwszej
        for (let i = menuLinks.length - 1; i >= 0; i--) {
            const item = menuLinks[i];
            const section = item.section;
            
            if (!section) continue;
            
            const sectionTop = section.offsetTop;
            const sectionBottom = sectionTop + section.offsetHeight;
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                item.link.classList.add('active');
                activeFound = true;
            } else {
                item.link.classList.remove('active');
            }
        }
        
        // Jeśli nie znaleziono aktywnej sekcji, aktywuj pierwszą (tylko jeśli jesteśmy na górze)
        if (!activeFound && menuLinks.length > 0 && window.scrollY < 200) {
            menuLinks[0].link.classList.add('active');
        }
    }

    // Dodaj klasę active na starcie
    setTimeout(updateActiveLink, 100);

    // Nasłuchuj scrolla
    window.addEventListener('scroll', updateActiveLink, { passive: true });
    
    // Aktualizuj po resize okna (zmiana wysokości sekcji)
    window.addEventListener('resize', updateActiveLink, { passive: true });

    // OBSŁUGA BEZPOŚREDNICH WEJŚĆ Z LINKAMI W URL
    if (window.location.hash) {
        setTimeout(() => {
            const targetElement = document.querySelector(window.location.hash);
            if (targetElement) {
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Ustaw aktywny link
                menuLinks.forEach(item => {
                    if (`#${item.id}` === window.location.hash) {
                        item.link.classList.add('active');
                    } else {
                        item.link.classList.remove('active');
                    }
                });
            }
        }, 300);
    }
});