-- ============================================================
-- Chytrá strava – databázové schéma
-- MySQL 5.7+ / MariaDB (XAMPP), import přes phpMyAdmin nebo CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS chytra_strava
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_czech_ci;

USE chytra_strava;

-- ------------------------------------------------------------
-- potraviny – základní katalog s výživovými hodnotami
-- Pozn.: hodnoty i ceny uvažujeme ZA 100 g, abychom mohli
--        jednoduše srovnávat "výhodnost" mezi potravinami.
--        (Případné budoucí vylepšení: sloupec hmotnost_obalu_g.)
-- ------------------------------------------------------------
CREATE TABLE potraviny (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nazev       VARCHAR(100)     NOT NULL,
    bilkoviny   DECIMAL(6,2)     NOT NULL,   -- g bílkovin / 100 g
    sacharidy   DECIMAL(6,2)     NOT NULL,   -- g sacharidů / 100 g
    tuky        DECIMAL(6,2)     NOT NULL,   -- g tuků / 100 g
    kalorie     DECIMAL(8,2)     NOT NULL,   -- kcal / 100 g
    bezna_cena  DECIMAL(6,2)     NOT NULL,   -- Kč / 100 g
    PRIMARY KEY (id),
    UNIQUE KEY unique_nazev (nazev)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ------------------------------------------------------------
-- akce_obchody – informace o slevách z jednotlivých obchodů
-- Každá potravina může být ve slevě ve více obchodech zároveň,
-- proto je unikátní kombinace (potravina + obchod) a CRON skript
-- pak může použít INSERT ... ON DUPLICATE KEY UPDATE
-- (při opakovaném spuštění se slevy přepíší, nikoli zdvojí).
-- INDEX na platnost_do urychluje filtr "aktuální slevy".
-- ------------------------------------------------------------
CREATE TABLE akce_obchody (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_potraviny INT UNSIGNED  NOT NULL,
    obchod       VARCHAR(50)   NOT NULL,   -- např. Tesco, Billa, Kaufland
    akcni_cena   DECIMAL(6,2)  NOT NULL,   -- Kč / 100 g (slevněná)
    platnost_do  DATE          NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_potravina_obchod (id_potraviny, obchod),
    INDEX idx_platnost_do (platnost_do),
    FOREIGN KEY (id_potraviny)
        REFERENCES potraviny (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ------------------------------------------------------------
-- uzivatele – cílové makroživiny nastavené uživatelem
-- (Pro přihlašování přidáme později sloupce email a hash_hesla.)
-- ------------------------------------------------------------
CREATE TABLE uzivatele (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    jmeno           VARCHAR(100)  NOT NULL,
    cilove_kalorie  INT UNSIGNED  NOT NULL,   -- kcal / den
    cil_bilkoviny   INT UNSIGNED  NOT NULL,   -- g / den
    cil_sacharidy   INT UNSIGNED  NOT NULL,   -- g / den
    cil_tuky        INT UNSIGNED  NOT NULL,   -- g / den
    PRIMARY KEY (id),
    UNIQUE KEY unique_jmeno (jmeno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ------------------------------------------------------------
-- Testovací data – přesně pro ukázku, až budeme ladit generátor
-- ------------------------------------------------------------
INSERT INTO potraviny (nazev, bilkoviny, sacharidy, tuky, kalorie, bezna_cena) VALUES
('Kuřecí prsa',      31.00,  0.00,  3.50, 165.00, 12.00),
('Tvaroh polotučný', 12.00,  4.00,  1.50,  85.00,  6.00),
('Vejce',            13.00,  0.70, 11.00, 155.00,  8.00),
('Sýr Eidam',        25.00,  1.00, 30.00, 380.00,  9.00),
('Ovesné vločky',    13.50, 60.00,  7.00, 380.00,  4.50),
('Rýže basmati',      7.00, 78.00,  1.00, 350.00,  4.00),
('Čočka červená',    25.00, 52.00,  1.00, 320.00,  5.50),
('Krabí tyčky',      10.00,  3.00,  1.00,  60.00,  3.00),
('Jogurt bílý 3,5%',  4.00,  5.00,  3.50,  70.00,  1.50);

INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do) VALUES
(1, 'Billa',     9.90, '2030-12-31'),
(2, 'Tesco',     4.90, '2030-12-31'),
(7, 'Kaufland',  4.20, '2030-12-31'),
(4, 'Tesco',     7.50, '2026-10-31');  -- prošlá sleva – má se ignorovat
