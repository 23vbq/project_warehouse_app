# Felgapol – Plan implementacji modułu magazynowego

## Stack technologiczny
- **Backend:** PHP 8.3 + Symfony 7
- **Baza danych:** MySQL 8.0
- **Serwer:** Apache 2.4
- **Środowisko:** Docker (docker-compose)
- **Frontend:** Twig + Bootstrap 5 + Chart.js (CDN)

---

## Zakres implementacji

Implementujemy **moduł magazynowy (WMS)** — jeden z pięciu modułów systemu opisanego w studium wykonalności. Pozostałe moduły (sprzedaż, produkcja, jakość, księgowość) są poza zakresem implementacji.

---

## Struktura projektu

```
felgapol/
├── docker-compose.yml
├── Dockerfile
├── .env
├── config/
├── src/
│   ├── Controller/
│   │   ├── DashboardController.php
│   │   ├── ProductController.php
│   │   ├── LocationController.php      # Lokalizacje
│   │   ├── ReceiptController.php       # PZ
│   │   ├── ReleaseController.php       # WZ
│   │   ├── RelocationController.php    # Relokacja
│   │   └── InventoryController.php     # Inwentaryzacja
│   ├── Entity/
│   │   ├── Product.php
│   │   ├── WarehouseLocation.php       # Lokalizacja magazynowa
│   │   ├── StockLevel.php              # Stan per produkt + lokalizacja
│   │   ├── Operation.php               # Klasa bazowa (InheritanceType)
│   │   ├── Receipt.php                 # Przyjęcie (extends Operation)
│   │   ├── Release.php                 # Wydanie (extends Operation)
│   │   ├── Relocation.php             # Relokacja (extends Operation)
│   │   ├── Worker.php                  # Pracownik
│   │   └── User.php                   # Magazynier (FK → Worker)
│   ├── Repository/
│   ├── Form/
│   ├── Service/
│   │   ├── StockService.php            # logika stanów, FIFO
│   │   └── DocumentService.php         # generowanie PDF
│   ├── Service/
│   │   ├── StockService.php            # logika stanów, FIFO
│   │   └── DocumentService.php         # generowanie PDF
│   └── EventListener/
│       └── LowStockListener.php        # powiadomienia o niskim stanie
├── templates/
│   ├── base.html.twig
│   ├── print_layout.html.twig     # layout do wydruku (bez UI)
│   ├── dashboard/
│   ├── product/
│   ├── location/
│   ├── receipt/
│   │   ├── index.html.twig
│   │   ├── show.html.twig
│   │   └── print.html.twig        # widok do wydruku
│   ├── release/
│   │   ├── index.html.twig
│   │   ├── show.html.twig
│   │   └── print.html.twig
│   ├── relocation/
│   │   ├── index.html.twig
│   │   ├── show.html.twig
│   │   └── print.html.twig
│   └── inventory/
├── migrations/
└── public/
```

---

## Model danych

Oparty na diagramie klas z projektu. W Symfony/Doctrine dziedziczenie `Operacja → Przyjęcie / Wydanie / Relokacja` realizowane przez `InheritanceType::JOINED`.

### Encje

#### `product`
| Kolumna | Typ | Opis |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(255) | Nazwa produktu |
| type | ENUM | `wheel` (felga) / `material` (surowiec) |
| unit | VARCHAR(20) | Jednostka miary (szt., kg, m) |
| min_stock_level | INT | Minimalny poziom stanu magazynowego |
| created_at | DATETIME | |

#### `warehouse_location` (Lokalizacja magazynowa)
| Kolumna | Typ | Opis |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(100) | Nazwa lokalizacji (np. A1, B3, Strefa-C) |

#### `stock_level` (Stan magazynowy)
| Kolumna | Typ | Opis |
|---|---|---|
| id | INT PK | |
| product_id | FK → product | |
| location_id | FK → warehouse_location | |
| quantity | INT | Stan na danej lokalizacji |
| updated_at | DATETIME | |

#### `worker` (Pracownik)
| Kolumna | Typ | Opis |
|---|---|---|
| id | INT PK | |
| first_name | VARCHAR(100) | |
| last_name | VARCHAR(100) | |

#### `user` (Magazynier — konto systemowe)
| Kolumna | Typ | Opis |
|---|---|---|
| id | INT PK | |
| worker_id | FK → worker | Powiązany pracownik |
| email | VARCHAR(255) UNIQUE | Login |
| password | VARCHAR(255) | Zahashowane |
| roles | JSON | `["ROLE_MAGAZYNIER"]` |
| is_active | BOOLEAN | |

#### `operation` (klasa bazowa — tabela wspólna)
| Kolumna | Typ | Opis |
|---|---|---|
| id | INT PK | |
| type | VARCHAR(20) | Discriminator: `receipt` / `release` / `relocation` |
| user_id | FK → user | Kto wykonał |
| product_id | FK → product | |
| quantity | INT | |
| date | DATETIME | |
| notes | TEXT | |

#### `receipt` (Przyjęcie — JOIN z operation)
| Kolumna | Typ | Opis |
|---|---|---|
| id | FK → operation | |
| location_id | FK → warehouse_location | Lokalizacja docelowa |
| document_number | VARCHAR(50) | Nr PZ |
| supplier | VARCHAR(255) | Dostawca |

#### `release` (Wydanie — JOIN z operation)
| Kolumna | Typ | Opis |
|---|---|---|
| id | FK → operation | |
| location_id | FK → warehouse_location | Lokalizacja źródłowa |
| document_number | VARCHAR(50) | Nr WZ |
| recipient | VARCHAR(255) | Odbiorca |

#### `relocation` (Relokacja — JOIN z operation)
| Kolumna | Typ | Opis |
|---|---|---|
| id | FK → operation | |
| location_from_id | FK → warehouse_location | Lokalizacja źródłowa |
| location_to_id | FK → warehouse_location | Lokalizacja docelowa |
| document_number | VARCHAR(50) | Nr MM |

---

## Role użytkowników

| Rola | Uprawnienia |
|---|---|
| `ROLE_WAREHOUSE_EMPLOYEE` | PZ, WZ, relokacje, przeglądanie stanów i produktów, inwentaryzacja |
| `ROLE_WAREHOUSE_MANAGER` | Wszystko co pracownik + CRUD produktów i lokalizacji, zarządzanie użytkownikami |

---

## Funkcjonalności do zaimplementowania

### 1. Uwierzytelnianie
- [ ] Login / logout (Symfony Security Bundle)
- [ ] Automatyczne wylogowanie po 15 min bezczynności (session lifetime)
- [ ] Strona logowania z walidacją

### 2. Dashboard

#### KPI (karty na górze strony)
- [ ] Łączna liczba produktów w magazynie
- [ ] Liczba lokalizacji
- [ ] Liczba operacji dzisiaj (PZ + WZ + MM)
- [ ] Liczba produktów poniżej minimum stanu (czerwona karta — klikalny alert)

#### Wykresy (Chart.js — ładowany przez CDN, zero zależności backendowych)
- [ ] **Słupkowy: operacje z ostatnich 30 dni** — PZ / WZ / MM per dzień (stacked bar)
- [ ] **Poziomy słupkowy: TOP 10 produktów wg stanu** — szybki przegląd co się kończy
- [ ] **Kołowy: podział typów operacji** — ile % to przyjęcia, wydania, relokacje

#### Alerty o niskim stanie
- [ ] Tabela na dashboardzie: produkt, aktualny stan, minimum, lokalizacja, różnica — posortowana od najgorszego
- [ ] Kolorowanie wierszy: czerwony (stan = 0), pomarańczowy (stan < minimum)
- [ ] Flash message przy każdym WZ jeśli po wydaniu stan spadnie poniżej minimum

#### Ostatnia aktywność
- [ ] Tabela: 10 ostatnich operacji (typ, produkt, ilość, kto, kiedy) z linkiem do dokumentu

### 3. Zarządzanie lokalizacjami
- [ ] Lista lokalizacji magazynowych (np. A1, B2, Strefa-C)
- [ ] Dodaj / edytuj / usuń lokalizację
- [ ] Widok lokalizacji: jakie produkty i ile na danej lokalizacji

### 4. Zarządzanie produktami
- [ ] Lista produktów z filtrowaniem po nazwie i typie (felga / materiał)
- [ ] Dodaj / edytuj produkt — pole `type` jako select: felga lub materiał
- [ ] Widok szczegółowy produktu (stan per lokalizacja, historia ruchów)
- [ ] Ustawienie minimalnego poziomu stanu i jednostki miary

### 5. Dokumenty PZ (przyjęcie)
- [ ] Lista PZ z filtrowaniem po dacie i numerze
- [ ] Formularz nowego PZ (nagłówek + pozycje dynamiczne JS)
- [ ] Automatyczna aktualizacja `stock_level` po zapisie
- [ ] Generowanie numeru PZ (np. `PZ/2026/001`)
- [ ] Podgląd / wydruk PDF

### 6. Dokumenty WZ (wydanie)
- [ ] Lista WZ z filtrowaniem
- [ ] Formularz nowego WZ (walidacja: czy jest wystarczający stan)
- [ ] Implementacja FIFO przy wydaniu (pobierz najstarszą partię)
- [ ] Automatyczna aktualizacja `stock_level` po zapisie
- [ ] Generowanie numeru WZ
- [ ] Podgląd / wydruk PDF

### 7. Stany magazynowe
- [ ] Tabela wszystkich produktów ze stanem aktualnym
- [ ] Kolorowe oznaczenie: zielony (OK), żółty (bliski minimum), czerwony (poniżej minimum)
- [ ] Automatyczne powiadomienie (flash message / e-mail) przy przekroczeniu minimum

### 8. Relokacja
- [ ] Formularz relokacji: produkt, lokalizacja źródłowa, lokalizacja docelowa, ilość
- [ ] Walidacja: czy jest wystarczający stan na lokalizacji źródłowej
- [ ] Aktualizacja `stock_level` na obu lokalizacjach
- [ ] Generowanie dokumentu MM (przesunięcie magazynowe)

### 9. Inwentaryzacja
- [ ] Formularz: wprowadź stan rzeczywisty dla każdego produktu
- [ ] Porównanie: stan systemowy vs rzeczywisty (różnica)
- [ ] Zapis korekty z datą i autorem

### 10. Generowanie dokumentów do wydruku
- [ ] Osobny template Twig `print_layout.html.twig` — brak menu, brak nawigacji, tylko treść
- [ ] CSS `@media print`: marginesy A4, ukrycie elementów UI, podział stron
- [ ] Przycisk „Drukuj" wywołuje `window.print()` lub otwiera `/pz/{id}/print` w nowej karcie
- [ ] Dokument PZ: nagłówek (nr, data, dostawca, lokalizacja), tabela pozycji, miejsce na podpis
- [ ] Dokument WZ: nagłówek (nr, data, odbiorca, lokalizacja), tabela pozycji, miejsce na podpis
- [ ] Dokument MM (relokacja): nagłówek (nr, data, lokalizacja źródłowa → docelowa), tabela pozycji


---

## Struktura menu bocznego

```
┌─────────────────────────┐
│  🏭 Felgapol            │
│  [avatar] Jan Kowalski  │
├─────────────────────────┤
│  📊 Dashboard           │
├─────────────────────────┤
│  MAGAZYN                │
│  📦 Stany magazynowe    │
│  📍 Lokalizacje         │
│  🔄 Relokacje           │
├─────────────────────────┤
│  DOKUMENTY              │
│  ⬇️  Przyjęcia (PZ)     │
│  ⬆️  Wydania (WZ)       │
├─────────────────────────┤
│  PRODUKTY               │
│  🔩 Lista produktów     │
│  ➕ Dodaj produkt       │
├─────────────────────────┤
│  RAPORTY                │
│  📋 Inwentaryzacja      │
├─────────────────────────┤
│  ADMIN                  │  ← widoczne tylko dla ROLE_WAREHOUSE_MANAGER
│  👤 Użytkownicy         │
├─────────────────────────┤
│  🚪 Wyloguj             │
└─────────────────────────┘
```

### Zasady widoczności
| Sekcja | ROLE_WAREHOUSE_EMPLOYEE | ROLE_WAREHOUSE_MANAGER |
|---|---|---|
| Dashboard | ✓ | ✓ |
| Stany magazynowe | ✓ | ✓ |
| Lokalizacje | podgląd | ✓ (CRUD) |
| Relokacje | ✓ | ✓ |
| Przyjęcia (PZ) | ✓ | ✓ |
| Wydania (WZ) | ✓ | ✓ |
| Lista produktów | podgląd | ✓ (CRUD) |
| Inwentaryzacja | ✓ | ✓ |
| Użytkownicy | ✗ | ✓ |

### Aktywny element
Bieżąca pozycja menu podświetlona (klasa `active` w Bootstrap), obsługiwana przez Twig:
```twig
<a class="nav-link {{ app.request.attributes.get('_route') starts with 'receipt' ? 'active' : '' }}"
   href="{{ path('receipt_index') }}">
  Przyjęcia (PZ)
</a>
```

---

## Etapy implementacji

### Etap 1 – Środowisko i szkielet (dzień 1)
1. `docker-compose.yml` – kontenery: php-apache, mysql
2. Instalacja Symfony, konfiguracja `.env`, połączenie z DB
3. Symfony Security – encja User, login/logout
4. Layout bazowy Twig (menu boczne, Bootstrap 5)

### Etap 2 – Encje i baza danych (dzień 1–2)
1. Wszystkie encje Doctrine (z `InheritanceType::JOINED` dla Operation)
2. Relacje: StockLevel per produkt+lokalizacja
3. Migracje (`doctrine:migrations:diff` + `migrate`)
4. DataFixtures – przykładowe produkty, lokalizacje, użytkownik admin

### Etap 3 – Produkty, lokalizacje i stany (dzień 2)
1. CRUD produktów
2. CRUD lokalizacji magazynowych
3. Widok stanów: per produkt (suma) i per produkt+lokalizacja
4. Kolorowanie stanów (OK / ostrzeżenie / krytyczny)
5. Powiadomienia o niskim stanie

### Etap 4 – Dokumenty PZ (dzień 3)
1. Formularz PZ z dynamicznymi pozycjami (JS)
2. Serwis aktualizacji stanów
3. Lista i podgląd PZ
4. Template do wydruku (`/receipt/{id}/print`)

### Etap 5 – Dokumenty WZ (dzień 3–4)
1. Formularz WZ z walidacją dostępności na wybranej lokalizacji
2. Lista i podgląd WZ
3. Template do wydruku (`/release/{id}/print`)

### Etap 5b – Relokacja (dzień 4)
1. Formularz relokacji z walidacją stanu na lokalizacji źródłowej
2. Aktualizacja `stock_level` na obu lokalizacjach
3. Template do wydruku (`/relocation/{id}/print`)

### Etap 6 – Dashboard i inwentaryzacja (dzień 4)
1. Dashboard z KPI
2. Moduł inwentaryzacji

### Etap 7 – Testy i poprawki (dzień 5)
1. Testy manualne wszystkich flow
2. Obsługa edge cases (zerowy stan, anulowanie)
3. Responsywność UI

---

## Uruchomienie projektu

```bash
git clone ...
cd felgapol
docker-compose up -d
docker-compose exec php bin/console doctrine:migrations:migrate
docker-compose exec php bin/console doctrine:fixtures:load
```

Dostęp: `http://localhost:8080`  
Login domyślny: `admin@felgapol.pl` / `admin123`
