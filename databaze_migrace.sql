-- ============================================================
-- MIGRACE č. 1 – rozšíření tabulky uzivatele a nové potraviny
-- Spustí se lokálně (XAMPP) i na hostingu (stejná struktura).
-- Idempotentní: klidně ji lze spustit vícekrát (IF NOT EXISTS,
-- INSERT IGNORE + unique klíče to umožňují).
--
-- Proč migrujeme?
--  1) login potřebuje e-mail, hash hesla a roli (admin/uzivatel)
--  2) generátor vyzkouší lépe na větší nabídce potravin
-- ============================================================

USE chytra_strava;

-- ---------- rozšíření tabulky uzivatele ----------
ALTER TABLE uzivatele
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NOT NULL DEFAULT '' AFTER jmeno,
    ADD COLUMN IF NOT EXISTS hash_hesla VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'uzivatel' AFTER email;

-- návazný unikátní index na e-mail (podle něj probíhá přihlášení)
CREATE UNIQUE INDEX IF NOT EXISTS unique_email ON uzivatele (email);

-- ---------- nové potraviny (větší nabídka pro generátor) ----------
INSERT IGNORE INTO potraviny (nazev, bilkoviny, sacharidy, tuky, kalorie, bezna_cena) VALUES
('Hovězí svíčková',   27.00,  0.00, 11.00, 209.00, 28.00),
('Losos',             20.00,  0.00, 13.00, 208.00, 32.00),
('Mléko čerstvé',      3.30,  4.80,  3.60,  64.00,  2.50),
('Banán',              1.10, 23.00,  0.30,  97.00,  3.00),
('Brambory',           2.00, 17.00,  0.10,  77.00,  2.00),
('Těstoviny celozrnné',13.00, 65.00,  2.50, 335.00,  5.50),
('Chléb celozrnný',    9.00, 46.00,  3.50, 253.00,  4.50),
('Quinoa',            14.00, 57.00,  6.00, 368.00, 22.00),
('Sýr Niva',          21.00,  0.50, 32.00, 371.00, 18.00),
('Pohanka',           13.00, 62.00,  3.40, 343.00,  6.50),
('Jablko',             0.30, 14.00,  0.20,  58.00,  3.50),
('Arašidy',           26.00,  9.00, 49.00, 567.00,  8.00);

-- ---------- slevy pro nové potraviny ----------
INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
    SELECT id, 'Kaufland', 24.90, '2030-12-31' FROM potraviny WHERE nazev = 'Hovězí svíčková'
    ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do);

INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
    SELECT id, 'Kaufland', 29.90, '2030-12-31' FROM potraviny WHERE nazev = 'Losos'
    ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do);

INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
    SELECT id, 'Billa', 1.99, '2030-12-31' FROM potraviny WHERE nazev = 'Mléko čerstvé'
    ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do);

INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
    SELECT id, 'Tesco', 3.90, '2030-12-31' FROM potraviny WHERE nazev = 'Těstoviny celozrnné'
    ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do);

INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
    SELECT id, 'Billa', 1.49, '2030-12-31' FROM potraviny WHERE nazev = 'Brambory'
    ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do);

-- ---------- ukázkové uživatelské účty ----------
-- Heslo "Admin123!"  -> hash níže (password_hash, bcrypt)
-- Heslo "Petr123!"   -> hash níže
-- Účty slouží pro prezentaci na obhajobě; hesla nikdy neuchováváme
-- v otevřené podobě, vždy jen HASH (password_hash / password_verify).
INSERT IGNORE INTO uzivatele (jmeno, email, hash_hesla, role, cilove_kalorie, cil_bilkoviny, cil_sacharidy, cil_tuky) VALUES
('Admin', 'admin@optimeal.cz', '$2y$12$opovmRFlCkFU42abs3UkKej.Wf3Apfitg5pH1dkcKQf.Y8RZo9PG6', 'admin',   2400, 160, 280, 80),
('Petr Novák', 'petr@optimeal.cz', '$2y$12$EK/EDIuQlbYzKmcqpEPEK.u3G1YROlgBA/EpZztTHzth3EW/z69zO', 'uzivatel', 2200, 150, 250, 70);
