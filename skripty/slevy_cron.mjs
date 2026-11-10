// ============================================================
// slevy_cron.mjs – CRON spouštěč aktualizace slev (GitHub Actions)
// ------------------------------------------------------------
// Problém, který řešíme: InfinityFree chrání web „browser integrity
// checkem“ – první požadavek bez cookies dostane malý JS skript
// (AES challenge). cron-job.org a cURL JS nespustí, takže se k
// update_slevy.php nikdy nedostanou. Node.js to umí: stáhne challenge,
// vypočítá cookie __test stejným algoritmem jako prohlížeč a odešle ji
// zpět – server pak pustí požadavek dál k našemu PHP skriptu.
//
// Použití:  node skripty/slevy_cron.mjs
// (cron: každý středu 03:00 Europe/Prague = 02:00 UTC)
// ============================================================

import { createDecipheriv } from 'node:crypto';

const URL_CILE =
  process.env.OPTIMEAL_URL ?? 'https://optimeal.site.je/update_slevy.php?token=slevy_cron_2026';

/**
 * Vyřeší InfinityFree anti-bot challenge stejně jako prohlížeč:
 * __test = hex( AES-128-CBC.dekryptuj(cipher, klíč=a, iv=b) )
 */
function vyresChallenge(klicHex, ivHex, cipherHex) {
  const dekoduje = createDecipheriv(
    'aes-128-cbc',
    Buffer.from(klicHex, 'hex'),
    Buffer.from(ivHex, 'hex')
  );
  dekoduje.setAutoPadding(false); // challenge používá přesně 16 bajtů
  const plaintext = Buffer.concat([
    dekoduje.update(Buffer.from(cipherHex, 'hex')),
    dekoduje.final(),
  ]);
  return `__test=${plaintext.toString('hex')}`;
}

/** Načte adresu, při challenge spočítá cookie a zkusí to znovu. */
async function stahni(url) {
  const hlavicky = { 'User-Agent': 'OptiMeal-cron/1.0 (GitHubActions)' };

  let odpoved = await fetch(url, { headers: hlavicky });
  let html = await odpoved.text();

  if (html.includes('toNumbers(')) {
    // server vrátil JS challenge – spočítáme cookie a zopakujeme požadavek
    const m = html.match(
      /toNumbers\("([0-9a-f]{32})"\),b=toNumbers\("([0-9a-f]{32})"\),c=toNumbers\("([0-9a-f]{32})"\)/
    );
    if (!m) {
      throw new Error('Challenge nemá očekávaný formát.');
    }
    const cookie = vyresChallenge(m[1], m[2], m[3]);
    console.log(`Vyřešena anti-bot challenge (cookie ${cookie.length} bajtů).`);
    odpoved = await fetch(url, { headers: { ...hlavicky, Cookie: cookie } });
    html = await odpoved.text();
  }

  return { status: odpoved.status, html };
}

/* ---------------- spuštění ---------------- */

const { status, html } = await stahni(URL_CILE);
console.log(`HTTP ${status}`);
console.log(html.trim());

// úspěch jen když stránka potvrdí „Aktualizace slev proběhla“
const vporadku = status === 200 && html.includes('Aktualizace slev prob');
process.exit(vporadku ? 0 : 1);
