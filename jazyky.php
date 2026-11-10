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
        ],

        'en' => [
            /* --- navigation --- */
            'nav_generator'      => 'Meal planner',
            'nav_slevy_prehled'  => 'Price overview',
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

            /* --- settings (modal) --- */
            'nastaveni_nazev'        => 'Settings',
            'nastaveni_zavrit'       => 'Close',
            'nastaveni_tema_nazev'   => 'Appearance',
            'nastaveni_tema_svetlo'  => 'Light',
            'nastaveni_tema_tma'     => 'Dark',
            'nastaveni_tema_system'  => 'System',
            'nastaveni_tema_poznamka' => '"System" automatically follows your operating system\'s appearance. Your choice is stored in the browser and applies to the whole app.',
        ],
    ];
}
