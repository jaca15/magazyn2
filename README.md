# Magazyn2 – System Zarządzania Magazynem

Prosta aplikacja PHP do zarządzania magazynem sprzętu, wypożyczeniami i zwrotami.

---

## Wymagania

- PHP 8.0+ z rozszerzeniami: `pdo_mysql`, `mbstring`, `gd` (zdjęcia)
- MySQL / MariaDB 5.7+
- (Opcjonalnie) Biblioteka [TCPDF](https://tcpdf.org/) do generowania protokołów PDF (`tcpdf/tcpdf.php`)

---

## Instalacja i uruchomienie

### 1. Sklonuj repozytorium

```bash
git clone https://github.com/jaca15/magazyn2.git
cd magazyn2
```

### 2. Utwórz wymagane katalogi runtime

```bash
mkdir -p logs protokoly zdjecia
chmod 0750 logs protokoly
chmod 0755 zdjecia
```

### 3. Skonfiguruj bazę danych

Utwórz bazę danych i zaimportuj schemat (plik SQL w repozytorium, jeśli dostępny).

### 4. Skonfiguruj zmienne środowiskowe

Ustaw następujące zmienne środowiskowe (np. w pliku `.env` lub konfiguracji serwera):

| Zmienna       | Opis                                    | Domyślnie |
|---------------|-----------------------------------------|-----------|
| `DB_HOST`     | Adres serwera bazy danych               | `localhost` |
| `DB_PORT`     | Port serwera bazy danych                | `3306` |
| `DB_NAME`     | Nazwa bazy danych                       | –         |
| `DB_USER`     | Użytkownik bazy danych                  | –         |
| `DB_PASSWORD` | Hasło do bazy danych                    | –         |

Zmienne są odczytywane w `polaczenie.php` przez `getenv()`.

### 5. Skonfiguruj uprawnienia plików

```bash
# Katalogi zapisu
chmod 0750 logs protokoly
chmod 0755 zdjecia
# Pliki PHP tylko do odczytu przez serwer www
find . -name "*.php" -exec chmod 0644 {} \;
```

### 6. Pierwsze uruchomienie – konfiguracja administratora

Otwórz `ustaw_haslo_admin.php` w przeglądarce (tylko gdy nie istnieje żaden administrator). Po utworzeniu konta administratora strona automatycznie blokuje kolejne wywołania.

---

## Model ról i uprawnień

| Rola          | Opis                                                            |
|---------------|-----------------------------------------------------------------|
| `admin`       | Pełny dostęp: użytkownicy, magazyny, kategorie, lokalizacje, sprzęt, wypożyczenia, zwroty |
| `magazynier`  | Dostęp do sprzętu, wypożyczeń i zwrotów; brak zarządzania słownikami i użytkownikami |
| `gość`        | Tylko podgląd wykazu sprzętu (tylko odczyt)                     |

Uprawnienia sprawdzane są przez funkcje z `auth.php`:
- `require_login()` – wymaga zalogowania
- `require_admin()` – wymaga roli `admin`
- `require_permission($perm, $msg)` – wymaga konkretnego uprawnienia (np. `issue_equipment`, `return_equipment`)

---

## Obsługa błędów

- Szczegóły wyjątków **nigdy** nie są pokazywane użytkownikowi – trafiają wyłącznie do `error_log()` (plik `logs/`).
- Odpowiedzi JSON dla endpointów AJAX zawierają wyłącznie bezpieczne komunikaty.

---

## Checklist bezpieczeństwa wdrożenia

Przed wdrożeniem produkcyjnym sprawdź:

- [ ] `DB_PASSWORD` i inne dane wrażliwe **nie są** w repozytorium (użyj zmiennych środowiskowych)
- [ ] Serwer działa po HTTPS – odkomentuj `ini_set('session.cookie_secure', '1')` w `auth.php`
- [ ] Katalog `logs/` jest niedostępny z zewnątrz (np. reguła `deny all` w nginx/Apache)
- [ ] Katalog `protokoly/` jest niedostępny bezpośrednio z zewnątrz (tylko przez `protokol_wydania.php`)
- [ ] Katalog `tcpdf/` jest niedostępny bezpośrednio z zewnątrz
- [ ] Plik `polaczenie.php` jest niedostępny bezpośrednio z zewnątrz
- [ ] `ustaw_haslo_admin.php` jest zablokowany po pierwszym uruchomieniu (automatyczne po utworzeniu admina)
- [ ] `error_reporting` ustawione na `E_ALL` tylko w środowisku deweloperskim, w produkcji `0` lub tylko logowanie
- [ ] Uprawnienia plików i katalogów są minimalne (patrz sekcja wyżej)
- [ ] Zainstalowana i działająca biblioteka TCPDF (jeśli wymagane protokoły PDF)

---

## Katalogi runtime

| Katalog       | Zawartość                                   | Wymagane uprawnienia |
|---------------|---------------------------------------------|----------------------|
| `logs/`       | Logi błędów aplikacji                       | `0750` (zapis przez PHP, brak dostępu z www) |
| `protokoly/`  | Wygenerowane protokoły PDF                  | `0750` (zapis przez PHP, brak dostępu z www) |
| `zdjecia/`    | Zdjęcia sprzętu                             | `0755` (odczyt przez www)                    |

---

## Zależności zewnętrzne

| Zależność | Ścieżka         | Wymagana przez             |
|-----------|-----------------|----------------------------|
| TCPDF     | `tcpdf/tcpdf.php` | `protokol_wydania.php`   |

Biblioteka TCPDF nie jest dołączona do repozytorium. Pobierz ze strony [tcpdf.org](https://tcpdf.org/) i umieść w katalogu `tcpdf/`.
