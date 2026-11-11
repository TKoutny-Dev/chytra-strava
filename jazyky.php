<?php
declare(strict_types=1);

/**
 * jazyky.php – mezinárodní vytvorenie OptiMealu (i18n)
 * ---------------------------------------------------------------
 * Aplikace pracuje s dvěma jazyky: cs (čeština) a en (angličtina).
 *
 * Jak se jazyk volí (v pořadí):
 *   1) ?lang=en v URL  ->  uloží se do session (a prompt trva na ni)
 *   2) jazyk uložený v session
 *   3) hlavička Accept-Language prohlížeče (cz/sk -> cs, ostatní -> en)
 *
 * Proč takhle? Svět používá angličtinu jako default,
 * ale český/slovenský prohlížeč dostane češtinu automaticky,
 * každý si ji pak přepne manuálně (neřešíme cookies, vše v session).
 */

/** Máme podporované jazyky */
const LANGJS_PODPOROVANE = ['cs', 'en'];

/**
 * Rozhodne a vrátí aktuální jazyk (vždy 'cs' nebo 'en').
 * Volá se po startu session (z spolecne.php).
 */
function aktualni_jazyk(): string
{
    // 1) explicitní přepínač v URL (?lang=en)
    $zUrl = $_GET['lang'] ?? '';
    if (is_string($zUrl) && in_array($zUrl, LANGJS_PODPOROVANE, true)) {
        $_SESSION['jazyk'] = $zUrl;
        return $zUrl;
    }

    // 2) dřívější volba uživatele v session
    $vSession = $_SESSION['jazyk'] ?? '';
    if (is_string($vSession) && in_array($vSession, LANGJS_PODPOROVANE, true)) {
        return $vSession;
    }

    // 3) podle prohlížeče (Accept-Language) – cs/sk -> cs, ostatní -> en
    $hlavicka = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $prvni = strtolower(trim(explode(',', $hlavicka)[0] ?? ''));
    $jazyk = str_starts_with($prvni, 'cs') || str_starts_with($prvni, 'sk') ? 'cs' : 'en';
    $_SESSION['jazyk'] = $jazyk;
    return $jazyk;
}

/** Doplní do současného URL parametr ?lang=xx (přepínač v navigaci). */
function jazyk_odkaz(string $lang): string
{
    $params = $_GET;
    $params['lang'] = $lang;
    return '?' . http_build_query($params);
}

/**
 * Přeloží klíč do aktivního jazyka + dosadí %%náhrady%%, podobně
 * jako printf: t('vitej', ['%jmeno%' => 'Petr']).
 */
function t(string $klic, array $nahradit = []): string
{
    static $cache = null;

    if ($cache === null) {
        $jazyk = aktualni_jazyk();
        $vse = LANGJS_SLOVNIKY();
        $cache = $vse[$jazyk] ?? $vse['en'];
    }

    $text = $cache[$klic] ?? $klic;
    foreach ($nahradit as $co => $za) {
        $text = str_replace($co, (string) $za, $text);
    }
    return $text;
}

/** Jazykové slovníky (každý klíč má obě varianty strany od sebe). */
function LANGJS_SLOVNIKY(): array
{
    return [

        'cs' => [
            /* --- navigace --- */
            'nav_generator'      => 'Generátor',
            'nav_slevy_prehled'  => 'Přehled slev',
            'nav_katalog'        => 'Katalog potravin',
            'nav_potraviny'      => 'Správa potravin',
            'nav_slevy'          => 'Správa slev',
            'nav_prihlasit'      => 'Přihlásit se',
            'nav_registrace'     => 'Registrace',
            'nav_odhlasit'       => 'Odhlásit',

            /* --- hlavní strana --- */
            'titulek_hlavni'  => 'OptiMeal – chytrá strava a nákupní seznam',
            'hero_nadpis'     => 'Chytrý generátor jídelníčku',
            'hero_odrazka'    => 'Ceny v reálném čase',
            'hero_podtext'    => 'Zadejte denní cíle a generátor sestaví jídelníček i nákupní seznam – <strong>s prioritou právě probíhajících slev v obchodech</strong>.',
            'cile_nadpis'     => 'Denní cíle',
            'profil_label'    => 'Profil uživatele (z databáze)',
            'profil_vlastni'  => '— vlastní cíle (ruční zadání) —',
            'profil_hint'     => 'Výběr profilu přepíše ručně zadané hodnoty.',
            'profil_auto_note' => 'Cíle jsou automaticky převzaty z vašeho profilu (výpočet z údajů o těle) – jídelníček vygenerován bez klikání.',
            'kcal_label'      => 'Kalorie (kcal)',
            'bilkoviny_label' => 'Bílkoviny (g)',
            'sacharidy_label' => 'Sacharidy (g)',
            'tuky_label'      => 'Tuky (g)',
            'tlacitko_generovat' => '🍽️ Vygenerovat jídelníček',
            'prazdno_zprava'  => 'Nastavte cíle výše (nebo vyberte profil) a klikněte na <strong>„Vygenerovat jídelníček"</strong>.',

            /* --- souhrn cílí vs. realita --- */
            'skutecnost_cile' => 'Skutečnost vs. cíle',
            'shoda_znacka'   => '%shoda%% shody',
            'metrika_kalorie'  => 'Kalorie',
            'metrika_bilkoviny' => 'Bílkoviny',
            'metrika_sacharidy' => 'Sacharidy',
            'metrika_tuky'      => 'Tuky',
            'jidelnicek_nadpis' => 'Jídelníček na den',
            'prazdny_jidlo'   => 'Pro toto jídlo se nenašly vhodné potraviny.',
            'denni_cena_jidla' => 'Cena jídla:',
            'sleva_badge'     => '🏷️ sleva:',
            'sleva_kratky' => 'sleva',
            'nakup_nadpis'    => '🛒 Nákupní seznam',
            'th_potravina'    => 'Potravina',
            'th_mnozstvi'     => 'Množství',
            'th_cena_100g'    => 'Cena / 100 g',
            'th_obchod'       => 'Obchod (nejlevnější)',
            'th_cena_celkem'  => 'Cena celkem',
            'celkem_den'      => 'Celkem den nákupu:',
            'poznamka_obchody' => 'Ve sloupci obchodu je vždy obchod s nejlevnější <strong>aktuální</strong> slevou (sleva musí být stále platná &ndash; <em>platnost_do ≥ dnešní datum</em>).',
            'tlacitko_tisk'   => '🖨️ Vytisknout seznam',

            /* --- meal names (generator data keys) --- */
            'jidlo_Snídaně'   => 'Snídaně',
            'jidlo_Oběd'      => 'Oběd',
            'jidlo_Svačina'   => 'Svačina',
            'jidlo_Večeře'    => 'Večeře',

            /* --- týdenní plán --- */
            'tyden_tlacitko'       => '📅 Týdenní plán',
            'tyden_nadpis'         => '📅 Týdenní jídelníček (Po–Ne)',
            'tyden_zpet'           => '← Zpět na denní plán',
            'tyden_metrika_kalorie' => 'Kalorie za týden',
            'tyden_metrika_prumer'  => 'Průměr na den',
            'tyden_metrika_cena'    => 'Nákup na celý týden',
            'tyden_metrika_cena_prumer' => 'Průměrný den',
            'tyden_souhrn_den'     => 'Součet dne',
            'celkem_tyden'         => 'Celkem za týden',
            'den_1'                => 'Pondělí',
            'den_2'                => 'Úterý',
            'den_3'                => 'Středa',
            'den_4'                => 'Čtvrtek',
            'den_5'                => 'Pátek',
            'den_6'                => 'Sobota',
            'den_7'                => 'Neděle',

            /* --- footer --- */
            'paticka_text'    => 'OptiMeal – maturitní projekt • PHP 8 + MySQL (PDO) + Bootstrap 5',
            'paticka_nahled'  => 'technický náhled dotazu',

            /* --- přihlášení --- */
            'prihl_title'      => 'Přihlášení – OptiMeal',
            'prihl_h1'         => '🥗 OptiMeal – přihlášení',
            'email_label'      => 'E-mail',
            'heslo_label'      => 'Heslo',
            'tlacitko_prihlasit' => 'Přihlásit se',
            'chyba_prihlaseni' => 'Neplatný e-mail nebo heslo.',
            'chyba_zamknuto'   => 'Příliš mnoho neúspěšných pokusů – přihlášení je dočasně zablokováno (ochrana proti hrubé silě). Zkuste to znovu za %minuty% min.',
            'chyba_csrf'       => 'Vypršela platnost formuláře, zkuste to prosím znovu.',
            'vitej_zpet'       => 'Vítejte zpět, %jmeno%!',
            'nemate_ucet'      => 'Nemáte účet?',
            'zaregistrujte_se' => 'Zaregistrujte se',
            'na_generator'     => 'Na generátor',

            /* --- registrace --- */
            'registr_title'    => 'Registrace – OptiMeal',
            'registr_h1'       => '🥗 OptiMeal – registrace',
            'jmeno_label'      => 'Jméno',
            'heslo_hint'       => 'Heslo (min. 8 znaků)',
            'tlacitko_vytvorit' => 'Vytvořit účet',
            'uz_mate_ucet'     => 'Už máte účet?',
            'prihlaste_se'     => 'Přihlaste se',
            'chyba_jmeno'      => 'Jméno musí mít 3–100 znaků.',
            'chyba_email'      => 'Zadejte platný e-mail (max. 150 znaků).',
            'chyba_heslo'      => 'Heslo musí mít alespoň 8 znaků.',
            'chyba_duplicita'  => 'Uživatel se stejným jménem nebo e-mailem už existuje.',
            'chyba_ulozeni'    => 'Registraci se nepovedlo uložit, zkuste to prosím později.',
            'registrace_ok'    => 'Registrace proběhla úspěšně. Vítejte, %jmeno%!',

            /* --- nastavení (modál) --- */
            'nastaveni_nazev'        => 'Nastavení',
            'nastaveni_zavrit'       => 'Zavřít',
            'nastaveni_tema_nazev'   => 'Vzhled',
            'nastaveni_tema_svetlo'  => 'Světlé',
            'nastaveni_tema_tma'     => 'Tmavé',
            'nastaveni_tema_system'  => 'Dle systému',
            'nastaveni_tema_poznamka' => 'Stav „Dle systému" se automaticky řídí vzhledem vašeho operačního systému. Volba se ukládá do prohlížeče a platí pro celou aplikaci.',

            /* --- katalog potravin (Open Food Facts) --- */
            'katalog_title'      => 'Katalog potravin – OptiMeal',
            'katalog_h1'         => '🌍 Katalog potravin z celého světa',
            'katalog_podtext'    => 'Hledáte potravinu, kterou v katalogu chybí? Vytáhneme ji z otevřené databáze <strong>Open Food Facts</strong> (tři milióny výrobků, stovky zemí) – stačí doplnit cenu a zařadí se do generátoru.',
            'katalog_hledat'     => 'Hledat potravinu',
            'katalog_tlacitko'   => '🔍 Vyhledat',
            'katalog_placeholder' => 'např. „Parma ham", „Greek yogurt", „鸡胸肉"…',
            'katalog_nic'        => 'Zadejte, co hledáte – výsledky se ukáží níže.',
            'katalog_hledam'     => 'Hledám…',
            'katalog_zadne'      => 'Nic se nenašlo. Zkuste jiný název (výrobky se hledají v jazycích zahraničních trhů).',
            'katalog_chyba_site' => 'Databázi Open Food Facts se nepovedlo oslovit. Zkontrolujte připojení a zkuste to znovu.',
            'katalog_vlozit'     => '➕ Vložit do katalogu',
            'katalog_modal_nazev' => 'Vložit potravinu do katalogu',
            'katalog_cena_label' => 'Běžná cena / 100 g (%mena%)',
            'katalog_cena_hint'  => 'Cenu zjistěte z místního obchodu – databáze ceny neobsahuje.',
            'katalog_zdroj'      => 'Zdroj dat: <a href="https://world.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a> (licence ODbL)',
            'katalog_pridano'    => 'Potravina %jmeno% z Open Food Facts byla uložena – už ji vidí generátor.',
            'katalog_duplicita'  => 'Potravina %jmeno% už v katalogu je.',
            'katalog_chyba_dat'  => 'Zkontrolujte hodnoty (0–100 g, kcal 0–900, cena > 0) a zkuste to znovu.',
            'katalog_nezadno'    => 'Výrobek nemá doplněné nutriční hodnoty – nelze ho uložit.',

            /* --- měna (nastavení) --- */
            'mena_nazev'      => 'Měna',
            'mena_poznamka'   => 'Kurzy se denně aktualizují dle ECB (automaticky přes GitHub Actions). Databáze stále vede ceny v CZK – zobrazení se přepočítá na celém webu a vstupy se zpětně převedou na koruny.',

            /* --- ruční zápis slev (admin) --- */
            'slevy_pridat_nazev'   => '➕ Zapsat slevu ručně',
            'slevy_form_nazev'     => 'Ruční zápis slevy',
            'slevy_form_potravina' => 'Potravina',
            'slevy_form_obchod'    => 'Obchod',
            'slevy_form_akcni_cena' => 'Akční cena / 100 g (%mena%)',
            'slevy_form_platnost_do' => 'Platnost do',
            'slevy_form_vlozit'    => '💾 Uložit slevu',
            'slevy_pridano'        => 'Sleva byla uložena – generátor ji hned začne využívat.',
            'slevy_smazat'         => 'Smazat',
            'slevy_smazano'        => 'Sleva byla odstraněna.',
            'slevy_chyba_dat'      => 'Slevu se nepovedlo uložit – zkontrolujte potravinu, obchod, cenu (> 0) a datum platnosti.',

            /* --- můj profil (tělo, cíle, heslo) --- */
            'nav_profil'           => 'Můj profil',
            'profil_title'         => 'Můj profil – OptiMeal',
            'profil_h1'            => '👤 Můj profil',
            'profil_moje_cile'     => 'Moje denní cíle',
            'profil_pouzit'        => 'Naplánovat den s mými cíli',
            'profil_telo_nadpis'   => '🧮 Spočítat cíle z mých údajů (Mifflin-St Jeor)',
            'profil_vaha'          => 'Váha',
            'profil_vyska'         => 'Výška',
            'profil_vek'           => 'Věk',
            'profil_pohlavi'       => 'Pohlaví',
            'profil_pohlavi_m'     => 'Muž',
            'profil_pohlavi_z'     => 'Žena',
            'profil_aktivita'      => 'Aktivita',
            'aktivita_sedava'      => 'Sedavá (práce vsedě, málo pohybu)',
            'aktivita_mirna'       => 'Mírná (pohyb 1–3× týdně)',
            'aktivita_aktivni'     => 'Aktivní (pohyb 3–5× týdně)',
            'aktivita_velmi'       => 'Velmi aktivní (trénink téměř denně)',
            'profil_cil'           => 'Cíl',
            'cil_zhubnout'         => 'Zhubnout (deficit ~15 %)',
            'cil_udrzet'           => 'Udržet váhu',
            'cil_zhodnotit'        => 'Přibrat (přebytek ~10 %)',
            'profil_vypocitat'     => '💾 Uložit a spočítat cíle',
            'profil_vysvetleni'    => 'Výpočet: BMR (Mifflin-St Jeor, nebo přesnější Katch-McArdle při známém % tělesného tuku) × faktor aktivity × korekce cíle; makronutrienty v poměru 30 % bílkovin / 40 % sacharidů / 30 % tuků. Zárukou je 100% přesná bilance – uvedené gramy makroživin dávají dohromady přesně uvedené kalorie (4·B + 4·S + 9·T = kcal). Cíle se uloží do vašeho profilu – generátor je pak nabídne na hlavní stránce automaticky.',
            'profil_ulozeno'       => 'Cíle spočítány a uloženy (%vzorec%): %kcal% kcal = %b% g bílkovin + %s% g sacharidů + %t% g tuků – bilance je přesná na 100 %.',
            'profil_chyba_dat'     => 'Zkontrolujte zadané údaje (váha 30–300 kg, výška 100–250 cm, věk 10–120, tuk 3–60 %).',
            'profil_telo_tuk'      => 'Tělesný tuk (%) – nepovinné',
            'profil_telo_tuk_hint' => 'Pokud znáte % tělesného tuku, použije se přesnější vzorec Katch-McArdle; necháte-li pole prázdné, počítá se Mifflin-St Jeor.',
            'profil_vzorec_msj'    => 'Mifflin-St Jeor',
            'profil_vzorec_km'     => 'Katch-McArdle (dle % tělesného tuku)',
            'profil_vzorec_uzito'  => 'Výpočet: %vzorec%',
            'profil_heslo_nadpis'  => 'Změna hesla',
            'profil_heslo_stare'   => 'Dosavadní heslo',
            'profil_heslo_nove'    => 'Nové heslo (min. 8 znaků)',
            'profil_heslo_potvrzeni' => 'Nové heslo znovu',
            'profil_heslo_zmenit'  => 'Změnit heslo',
            'profil_heslo_ok'      => 'Heslo bylo změněno. Příště se přihlaste novým heslem.',
            'profil_heslo_spatne'  => 'Dosavadní heslo není správné – heslo jsme nezměnili.',
            'profil_heslo_kratke'  => 'Nové heslo musí mít alespoň 8 znaků.',
            'profil_heslo_rozdel'  => 'Nové heslo a jeho potvrzení se neshodují.',

            /* --- mé uložené plány --- */
            'nav_plany'            => 'Mé plány',
            'plany_title'          => 'Mé plány – OptiMeal',
            'plany_h1'             => '🗂️ Mé uložené plány',
            'plany_podtext'        => 'Vygenerované jídelníčky (denní i týdenní) si můžete uložit a kdykoliv se k nim vrátit.',
            'plany_prazdno'        => 'Zatím nemáte žádný uložený plán. Na hlavní stránce vygenerujte jídelníček a klikněte na <strong>„Uložit plán"</strong>.',
            'plany_th_datum'       => 'Vytvořeno',
            'plany_th_typ'         => 'Typ',
            'plany_th_cile'        => 'Cíle plánu',
            'th_akce'              => 'Akce',
            'plany_typ_den'        => 'Denní plán',
            'plany_typ_tyden'      => 'Týdenní plán',
            'plany_otevrit'        => 'Otevřít',
            'plany_potvrdit_smazani' => 'Opravdu smazat tento plán?',
            'plany_smazano'        => 'Plán byl smazán.',
            'plany_novy'           => '＋ Nový plán vygenerujete na hlavní stránce',
            'plany_zpet'           => '← Zpět na seznam plánů',
            'plany_zalozeno'       => 'Vytvořeno',
            'plan_ulozit_tlacitko' => 'Uložit plán',
            'plan_pouzit_cile'     => 'Použít cíle tohoto plánu',
            'th_usetreno'          => 'Ušetřeno',
            'nakup_usetreno'       => '💰 Díky dnešním slevám šetříte %castka% – generátor upřednostnil zlevněné potraviny.',
            'nakup_usetreno_kratce' => 'Ušetřeno slevami:',
            'plan_ulozen'          => 'Plán byl uložen do vaší historie – najdete ho v sekci „Mé plány".',
            'plan_nenalezen'       => 'Plán nebyl nalezen (nebo není váš).',
            'plan_vyzaduje_prihlaseni' => 'Ukládání plánů vyžaduje přihlášení.',
        ],

        'en' => [
            /* --- navigation --- */
            'nav_generator'      => 'Meal planner',
            'nav_slevy_prehled'  => 'Price overview',
            'nav_katalog'        => 'Food catalog',
            'nav_potraviny'      => 'Food management',
            'nav_slevy'          => 'Discount automation',
            'nav_prihlasit'      => 'Log in',
            'nav_registrace'     => 'Sign up',
            'nav_odhlasit'       => 'Log out',

            /* --- main page --- */
            'titulek_hlavni'  => 'OptiMeal – smart diet & shopping list',
            'hero_nadpis'     => 'Smart meal-plan generator',
            'hero_odrazka'    => 'Live sale prices',
            'hero_podtext'    => 'Enter your daily targets and the planner builds a personal meal plan and shopping list – <strong>automatically prioritising groceries that are on sale right now</strong>.',
            'cile_nadpis'     => 'Daily targets',
            'profil_label'    => 'User profile (from database)',
            'profil_vlastni'  => '— custom targets (manual entry) —',
            'profil_hint'     => 'Picking a profile overrides the values entered by hand.',
            'profil_auto_note' => 'Targets are taken automatically from your profile (calculated from your body data) – the meal plan was generated with no clicks.',
            'kcal_label'      => 'Calories (kcal)',
            'bilkoviny_label' => 'Protein (g)',
            'sacharidy_label' => 'Carbs (g)',
            'tuky_label'      => 'Fat (g)',
            'tlacitko_generovat' => '🍽️ Generate meal plan',
            'prazdno_zprava'  => 'Set your targets above (or pick a profile) and click <strong>"Generate meal plan"</strong>.',

            /* --- actual vs. targets --- */
            'skutecnost_cile' => 'Actual vs. targets',
            'shoda_znacka'   => '%shoda%% match',
            'metrika_kalorie'  => 'Calories',
            'metrika_bilkoviny' => 'Protein',
            'metrika_sacharidy' => 'Carbs',
            'metrika_tuky'      => 'Fat',
            'jidelnicek_nadpis' => 'Meal plan for the day',
            'prazdny_jidlo'   => 'No suitable foods were found for this meal.',
            'denni_cena_jidla' => 'Price of the meal:',
            'sleva_badge'     => '🏷️ sale:',
            'sleva_kratky' => 'sale',
            'nakup_nadpis'    => '🛒 Shopping list',
            'th_potravina'    => 'Item',
            'th_mnozstvi'     => 'Amount',
            'th_cena_100g'    => 'Price / 100 g',
            'th_obchod'       => 'Store (cheapest)',
            'th_cena_celkem'  => 'Total price',
            'celkem_den'      => 'Total for the day:',
            'poznamka_obchody' => 'The store column always shows the store with the lowest <strong>currently valid</strong> discount (the sale must still be active &ndash; <em>valid until ≥ today</em>).',
            'tlacitko_tisk'   => '🖨️ Print list',

            /* --- meal names (generator data keys) --- */
            'jidlo_Snídaně'   => 'Breakfast',
            'jidlo_Oběd'      => 'Lunch',
            'jidlo_Svačina'   => 'Snack',
            'jidlo_Večeře'    => 'Dinner',

            /* --- weekly plan --- */
            'tyden_tlacitko'       => '📅 Weekly plan',
            'tyden_nadpis'         => '📅 Weekly meal plan (Mon–Sun)',
            'tyden_zpet'           => '← Back to the daily plan',
            'tyden_metrika_kalorie' => 'Calories for the week',
            'tyden_metrika_prumer'  => 'Average per day',
            'tyden_metrika_cena'    => 'Shopping for the whole week',
            'tyden_metrika_cena_prumer' => 'Average day',
            'tyden_souhrn_den'     => 'Day total',
            'celkem_tyden'         => 'Total for the week',
            'den_1'                => 'Monday',
            'den_2'                => 'Tuesday',
            'den_3'                => 'Wednesday',
            'den_4'                => 'Thursday',
            'den_5'                => 'Friday',
            'den_6'                => 'Saturday',
            'den_7'                => 'Sunday',

            /* --- footer --- */
            'paticka_text'    => 'OptiMeal • PHP 8 + MySQL (PDO) + Bootstrap 5',
            'paticka_nahled'  => 'technical SQL preview',

            /* --- login --- */
            'prihl_title'      => 'Log in – OptiMeal',
            'prihl_h1'         => '🥗 OptiMeal – log in',
            'email_label'      => 'Email',
            'heslo_label'      => 'Password',
            'tlacitko_prihlasit' => 'Log in',
            'chyba_prihlaseni' => 'Invalid email or password.',
            'chyba_zamknuto'   => 'Too many failed attempts – login is temporarily locked (brute-force protection). Try again in %minuty% min.',
            'chyba_csrf'       => 'The form has expired, please try again.',
            'vitej_zpet'       => 'Welcome back, %jmeno%!',
            'nemate_ucet'      => 'No account yet?',
            'zaregistrujte_se' => 'Sign up',
            'na_generator'     => 'Back to planner',

            /* --- registration --- */
            'registr_title'    => 'Sign up – OptiMeal',
            'registr_h1'       => '🥗 OptiMeal – sign up',
            'jmeno_label'      => 'Name',
            'heslo_hint'       => 'Password (min. 8 characters)',
            'tlacitko_vytvorit' => 'Create account',
            'uz_mate_ucet'     => 'Already have an account?',
            'prihlaste_se'     => 'Log in',
            'chyba_jmeno'      => 'Name must be 3–100 characters long.',
            'chyba_email'      => 'Enter a valid email (max. 150 characters).',
            'chyba_heslo'      => 'Password must be at least 8 characters long.',
            'chyba_duplicita'  => 'A user with this name or email already exists.',
            'chyba_ulozeni'    => 'Could not save the registration, please try again later.',
            'registrace_ok'    => 'Registration successful. Welcome, %jmeno%!',

            /* --- food catalog (Open Food Facts) --- */
            'katalog_title'      => 'Food catalog – OptiMeal',
            'katalog_h1'         => '🌍 Food catalog from around the world',
            'katalog_podtext'    => 'Can\'t find a food in the catalog? We pull it from the open <strong>Open Food Facts</strong> database (3 million products, hundreds of countries) – just add a price and it joins the planner.',
            'katalog_hledat'     => 'Search for a food',
            'katalog_tlacitko'   => '🔍 Search',
            'katalog_placeholder' => 'e.g. "Parma ham", "Greek yogurt", "鸡胸肉"…',
            'katalog_nic'        => 'Enter what you\'re looking for – results will appear below.',
            'katalog_hledam'     => 'Searching…',
            'katalog_zadne'      => 'Nothing found. Try another name (products are searched in the languages of their markets).',
            'katalog_chyba_site' => 'Could not reach the Open Food Facts database. Check your connection and try again.',
            'katalog_vlozit'     => '➕ Add to catalog',
            'katalog_modal_nazev' => 'Add a food to the catalog',
            'katalog_cena_label' => 'Regular price / 100 g (%mena%)',
            'katalog_cena_hint'  => 'Get the price from your local store – the database contains no prices.',
            'katalog_zdroj'      => 'Data source: <a href="https://world.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a> (ODbL licence)',
            'katalog_pridano'    => 'Food %jmeno% from Open Food Facts was saved – the planner can use it now.',
            'katalog_duplicita'  => 'The food %jmeno% is already in the catalog.',
            'katalog_chyba_dat'  => 'Check the values (0–100 g, kcal 0–900, price > 0) and try again.',
            'katalog_nezadno'    => 'The product has no nutrition data – it cannot be saved.',

            /* --- settings (modal) --- */
            'nastaveni_nazev'        => 'Settings',
            'nastaveni_zavrit'       => 'Close',
            'nastaveni_tema_nazev'   => 'Appearance',
            'nastaveni_tema_svetlo'  => 'Light',
            'nastaveni_tema_tma'     => 'Dark',
            'nastaveni_tema_system'  => 'System',
            'nastaveni_tema_poznamka' => '"System" automatically follows your operating system\'s appearance. Your choice is stored in the browser and applies to the whole app.',

            /* --- currency (settings) --- */
            'mena_nazev'      => 'Currency',
            'mena_poznamka'   => 'Exchange rates are updated daily from ECB rates (automatically via GitHub Actions). The database still stores prices in CZK – the display is converted across the whole site and inputs are converted back to CZK.',

            /* --- manual discount entry (admin) --- */
            'slevy_pridat_nazev'   => '➕ Enter a discount manually',
            'slevy_form_nazev'     => 'Manual discount entry',
            'slevy_form_potravina' => 'Food',
            'slevy_form_obchod'    => 'Store',
            'slevy_form_akcni_cena' => 'Sale price / 100 g (%mena%)',
            'slevy_form_platnost_do' => 'Valid until',
            'slevy_form_vlozit'    => '💾 Save discount',
            'slevy_pridano'        => 'Discount saved – the planner will use it right away.',
            'slevy_smazat'         => 'Delete',
            'slevy_smazano'        => 'Discount removed.',
            'slevy_chyba_dat'      => 'Could not save the discount – check the food, store, price (> 0) and validity date.',

            /* --- my profile (body data, targets, password) --- */
            'nav_profil'           => 'My profile',
            'profil_title'         => 'My profile – OptiMeal',
            'profil_h1'            => '👤 My profile',
            'profil_moje_cile'     => 'My daily targets',
            'profil_pouzit'        => 'Plan a day with my targets',
            'profil_telo_nadpis'   => '🧮 Calculate targets from my data (Mifflin-St Jeor)',
            'profil_vaha'          => 'Weight',
            'profil_vyska'         => 'Height',
            'profil_vek'           => 'Age',
            'profil_pohlavi'       => 'Sex',
            'profil_pohlavi_m'     => 'Male',
            'profil_pohlavi_z'     => 'Female',
            'profil_aktivita'      => 'Activity',
            'aktivita_sedava'      => 'Sedentary (desk job, little movement)',
            'aktivita_mirna'       => 'Light (exercise 1–3× a week)',
            'aktivita_aktivni'     => 'Active (exercise 3–5× a week)',
            'aktivita_velmi'       => 'Very active (training almost daily)',
            'profil_cil'           => 'Goal',
            'cil_zhubnout'         => 'Lose weight (~15 % deficit)',
            'cil_udrzet'           => 'Maintain weight',
            'cil_zhodnotit'        => 'Gain weight (~10 % surplus)',
            'profil_vypocitat'     => '💾 Save and calculate targets',
            'profil_vysvetleni'    => 'Formula: BMR (Mifflin-St Jeor, or the more accurate Katch-McArdle when body-fat % is known) × activity factor × goal correction; macros split 30 % protein / 40 % carbs / 30 % fat. Guaranteed 100 % exact balance – the macro grams add up to exactly the stated calories (4·P + 4·C + 9·F = kcal). Targets are saved to your profile – the planner applies them automatically on the main page.',
            'profil_ulozeno'       => 'Targets calculated and saved (%vzorec%): %kcal% kcal = %b% g protein + %s% g carbs + %t% g fat – the balance is exact to 100 %.',
            'profil_chyba_dat'     => 'Check your data (weight 30–300 kg, height 100–250 cm, age 10–120, body fat 3–60 %).',
            'profil_telo_tuk'      => 'Body fat (%) – optional',
            'profil_telo_tuk_hint' => 'If you know your body-fat %, the more accurate Katch-McArdle formula is used; leave blank to use Mifflin-St Jeor.',
            'profil_vzorec_msj'    => 'Mifflin-St Jeor',
            'profil_vzorec_km'     => 'Katch-McArdle (from body-fat %)',
            'profil_vzorec_uzito'  => 'Formula: %vzorec%',
            'profil_heslo_nadpis'  => 'Change password',
            'profil_heslo_stare'   => 'Current password',
            'profil_heslo_nove'    => 'New password (min. 8 characters)',
            'profil_heslo_potvrzeni' => 'New password again',
            'profil_heslo_zmenit'  => 'Change password',
            'profil_heslo_ok'      => 'Password changed. Use the new password next time you log in.',
            'profil_heslo_spatne'  => 'Current password is not correct – the password was not changed.',
            'profil_heslo_kratke'  => 'The new password must have at least 8 characters.',
            'profil_heslo_rozdel'  => 'The new password and its confirmation do not match.',

            /* --- my saved plans --- */
            'nav_plany'            => 'My plans',
            'plany_title'          => 'My plans – OptiMeal',
            'plany_h1'             => '🗂️ My saved plans',
            'plany_podtext'        => 'Save generated meal plans (daily or weekly) and come back to them any time.',
            'plany_prazdno'        => 'You have no saved plans yet. Generate a meal plan on the main page and click <strong>"Save plan"</strong>.',
            'plany_th_datum'       => 'Created',
            'plany_th_typ'         => 'Type',
            'plany_th_cile'        => 'Plan targets',
            'th_akce'              => 'Actions',
            'plany_typ_den'        => 'Daily plan',
            'plany_typ_tyden'      => 'Weekly plan',
            'plany_otevrit'        => 'Open',
            'plany_potvrdit_smazani' => 'Really delete this plan?',
            'plany_smazano'        => 'The plan was deleted.',
            'plany_novy'           => '＋ Generate a new plan on the main page',
            'plany_zpet'           => '← Back to the plan list',
            'plany_zalozeno'       => 'Created',
            'plan_ulozit_tlacitko' => 'Save plan',
            'plan_pouzit_cile'     => 'Use this plan\'s targets',
            'th_usetreno'          => 'Saved',
            'nakup_usetreno'       => '💰 Thanks to today\'s discounts you save %castka% – the planner prioritised discounted groceries.',
            'nakup_usetreno_kratce' => 'Saved by discounts:',
            'plan_ulozen'          => 'The plan was saved to your history – find it under "My plans".',
            'plan_nenalezen'       => 'The plan was not found (or is not yours).',
            'plan_vyzaduje_prihlaseni' => 'Saving plans requires logging in.',
        ],
    ];
}
