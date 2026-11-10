/* ============================================================
   motiv.js – přepínač motivu (světlo / tma / systém)
   ------------------------------------------------------------
   - uloží volbu do localStorage (přežije reload i zavření)
   - „systém" reaguje živě na změnu tématu operačního systému
    - synchronizuje se mezi otevřenými taby aplikace
   - nastavuje <html data-bs-theme="..."> (Bootstrap 5.3 + CSS vars)
   - brání probliknutí špatného tématu: mini-skript v <head>
     každé stránky už motiv nastaví PŘED prvním vykreslením,
     tento soubor jen doplní interaktivitu modálu.
   ============================================================ */

(function () {
    'use strict';

    const KLIC = 'optimeal-motiv';              // localStorage klíč
    const STAVY = ['light', 'dark', 'system']; // povolené stavy

    /* ---------- pomocníci ---------- */

    const validni = (v) => STAVY.includes(v);

    function ulozenyStav() {
        try {
            return validni(localStorage.getItem(KLIC)) ? localStorage.getItem(KLIC) : 'system';
        } catch {
            // localStorage může být blokován (režim bez cookies) – bezpečný default
            return 'system';
        }
    }

    /** preferuje-li OS tmavé téma? */
    function osChceVzorec() {
        return window.matchMedia('(prefers-color-scheme: dark)');
    }

    /** vybere barvu pro zvolený stav */
    function vyberBarvu(stav) {
        if (stav === 'dark') return 'dark';
        if (stav === 'light') return 'light';
        return osChceVzorec().matches ? 'dark' : 'light';
    }

    /** aplikuje téma na dokument (bez uložení) */
    function aplikuj(stav) {
        const barva = vyberBarvu(stav);
        document.documentElement.setAttribute('data-bs-theme', barva);
        document.documentElement.setAttribute('data-motiv', stav);

        // barva lišty mobilních prohlížečů (iOS Safari/Chrome Android)
        let meta = document.querySelector('meta[name="theme-color"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('name', 'theme-color');
            document.head.appendChild(meta);
        }
        meta.setAttribute('content', barva === 'dark' ? '#0d1310' : '#16a34a');
    }

    /* ---------- aplikace tématu ---------- */

    let aktualniStav = ulozenyStav();

    window.addEventListener('storage', (e) => {
        // jiný tab změnil téma – sladíme se (žádné konflikty mezi taby)
        if (e.key === KLIC && validni(e.newValue)) {
            aktualniStav = e.newValue;
            aplikuj(aktualniStav);
            oznamUI(aktualniStav);
        }
    });

    // „systém“ = živě reagujeme na OS (uživatel přepne tmavý režim ve Windows/macOS)
    osChceVzorec().addEventListener('change', () => {
        if (aktualniStav === 'system') aplikuj(aktualniStav);
    });

    /* ---------- UI modálu Nastavení ---------- */

    /** povolí/aktivuje správnou možnost v přepínači */
    function oznamUI(stav) {
        document.querySelectorAll('.om-moznost-tematu').forEach((tlacitko) => {
            const jeAktivni = tlacitko.dataset.motiv === stav;
            tlacitko.classList.toggle('aktivni', jeAktivni);
            tlacitko.setAttribute('aria-pressed', jeAktivni ? 'true' : 'false');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        aplikuj(aktualniStav);

        document.querySelectorAll('button[data-motiv], label[data-motiv], .om-moznost-tematu').forEach((tlacitko) => {
            tlacitko.addEventListener('click', (e) => {
                const novy = tlacitko.dataset.motiv;
                if (!validni(novy)) return;
                if (tlacitko.tagName === 'LABEL') {
                    e.preventDefault();
                }
                aktualniStav = novy;
                aplikuj(novy);
                try { localStorage.setItem(KLIC, novy); } catch { /* ukryto – neblokujeme UI */ }
                oznamUI(aktualniStav);
            });
        });

        oznamUI(aktualniStav);
    });
})();
