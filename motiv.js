/* ============================================================
   motiv.js – přepínač motivu (světlo / tma / systém) + interakce
   ------------------------------------------------------------
   - uloží volbu do localStorage (přežije reload i zavření)
   - „systém" reaguje živě na změnu tématu operačního systému
   - synchronizuje se mezi otevřenými taby aplikace
   - nastavuje <html data-bs-theme="..."> (Bootstrap 5.3 + CSS vars)
   - VÝCHOZÍ MOTIV = TMAVÝ (monochromní identita aplikace)
   - navíc: spotlight za kurzorem na kartách (CSS proměnné --mx/--my)
           a plynulé vjezdové animace při scrollu (IntersectionObserver)
   ============================================================ */

(function () {
    'use strict';

    const KLIC = 'optimeal-motiv';              // localStorage klíč
    const STAVY = ['light', 'dark', 'system']; // povolené stavy
    const VYCHOZI = 'dark';                     // tmavý motiv dominuje

    /* ---------- pomocníci ---------- */

    const validni = (v) => STAVY.includes(v);

    function ulozenyStav() {
        try {
            return validni(localStorage.getItem(KLIC)) ? localStorage.getItem(KLIC) : VYCHOZI;
        } catch {
            // localStorage může být blokován (režim bez cookies) – bezpečný default
            return VYCHOZI;
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
        meta.setAttribute('content', barva === 'dark' ? '#0a0a0a' : '#ffffff');
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
            // přepínač motivu má data-motiv, přepínač měny ne – ten nijak neovlivňujeme
            if (!tlacitko.dataset.motiv) return;
            const jeAktivni = tlacitko.dataset.motiv === stav;
            tlacitko.classList.toggle('aktivni', jeAktivni);
            tlacitko.setAttribute('aria-pressed', jeAktivni ? 'true' : 'false');
        });
    }

    /* ============================================================
       SPOTLIGHT – světelná skvrna sledující kurzor
       nad cílovými plochami nastavujeme CSS proměnné --mx a --my,
       samotný efekt kreslí styl.css (radial gradient ::after)
       ============================================================ */

    function nastavSpotlight() {
        // jen zařízení s reálným kurzorem; dotykové displeje přeskočíme
        if (!window.matchMedia('(hover: hover) and (prefers-reduced-motion: no-preference)').matches) {
            return;
        }

        const CILE = '.card, .karta-jidla';
        let cile = null;
        let cekajici = null; // rAF throttling – max 1 přepočet na snímek

        document.addEventListener('pointermove', (e) => {
            cekajici = e;
            if (cekajici) requestAnimationFrame(prekresli);
        }, { passive: true });

        function prekresli() {
            const e = cekajici;
            cekajici = null;
            if (!e) return;

            // obnovíme seznam cílů jen občas (přidávání karet je vzácné)
            if (!cile) {
                cile = Array.from(document.querySelectorAll(CILE));
            }

            for (const cil of cile) {
                if (cil.contains(e.target) || e.target === cil) {
                    const r = cil.getBoundingClientRect();
                    // kurzor opustil plochu – rozsvítíme jen když je uvnitř
                    if (e.clientX >= r.left && e.clientX <= r.right &&
                        e.clientY >= r.top && e.clientY <= r.bottom) {
                        cil.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                        cil.style.setProperty('--my', (e.clientY - r.top) + 'px');
                    }
                }
            }
        }
    }

    /* ============================================================
       VJEZDOVÉ ANIMACE – prvky skloubnou nahoru, když se objeví
       v zorném poli (při načtení i při scrollu). Bez JS se všecko
       zobrazí normálně (styl.css skrývá jen označené .om-ceka).
       ============================================================ */

    function nastavOdhavani() {
        if (!('IntersectionObserver' in window)) return; // staré prohlížeče – nic neskryváme
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const cile = document.querySelectorAll('.card, .karta-jidla, .om-hero');

        const pozorovac = new IntersectionObserver((vstupy) => {
            vstupy.forEach((vstup) => {
                if (!vstup.isIntersecting) return;
                const prvek = vstup.target;
                // lehké rozestoupení podle pořadí v sourozenecké řadě
                const sloupce = prvek.parentElement
                    ? Array.from(prvek.parentElement.children).indexOf(prvek)
                    : 0;
                prvek.style.animationDelay = (Math.max(0, sloupce % 6) * 55) + 'ms';
                prvek.classList.add('om-odhaleno');
                prvek.classList.remove('om-ceka');
                pozorovac.unobserve(prvek);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        cile.forEach((prvek) => {
            // uvnitř modálů animace nechceme (mizely by před otevřením)
            if (prvek.closest('.modal')) return;
            prvek.classList.add('om-ceka');
            pozorovac.observe(prvek);
        });
    }

    /* ---------- start ---------- */

    document.addEventListener('DOMContentLoaded', () => {
        aplikuj(aktualniStav);

        document.querySelectorAll('button[data-motiv], label[data-motiv], .om-moznost-tematu[data-motiv]').forEach((tlacitko) => {
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
        nastavSpotlight();
        nastavOdhavani();
    });
})();
