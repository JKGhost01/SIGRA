# SIGRA · Monitoraggio sismico globale e rete civica

**v1.4.0.0 · Mappa mondiale ed esperienza mobile** · `20261120.1` · 2026-11-20

> Versione stabile con mappa mobile, zonazione globale della pericolosità sismica e catalogo unificato. [1.4.0.0](docs/PRODUCCION.md)

[Español](README.md) · [English](README.en.md) · [Português](README.pt.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Italiano](README.it.md) · [日本語](README.ja.md) · [简体中文](README.zh-CN.md) · [العربية](README.ar.md)

Questa guida introduce il progetto e l’installazione in italiano. La [documentazione completa](README.md) e le guide tecniche sono mantenute in spagnolo. Comandi, percorsi e contratti API conservano i nomi originali. L’interfaccia web è disponibile in spagnolo e inglese.

## Panoramica

SIGRA è un’applicazione PHP open source che riunisce i terremoti pubblicati da **SGC, USGS, EMSC, GeoNet, GEOFON/GFZ e INGV** in una mappa consultabile, con archivio, filtri, API JSON e segnalazioni private dei cittadini. Il filtro di prossimità permette di cercare eventi intorno a una posizione scelta.

La rete di sensori degli smartphone è **sperimentale**. SIGRA non prevede i terremoti, non garantisce allerte anticipate e non sostituisce le autorità o i sistemi ufficiali. Il client Android è sviluppato separatamente in [SIGRA_MOBILE](https://github.com/JKGhost01/SIGRA_MOBILE).

## Funzioni e fonti

- Mappa Leaflet, archivio, schede dettagliate e filtri per luogo, fonte, magnitudo e distanza.
- SGC per la Colombia; USGS, EMSC e GEOFON per una copertura globale con limiti specifici.
- GeoNet per la Nuova Zelanda; INGV per l’Italia e le aree circostanti.
- API per web e Android, segnalazioni private e PWA installabile.
- Normalizzazione e correlazione dei dati; conservazione degli eventi salvati quando una fonte non è disponibile.

Copertura e frequenza variano tra i cataloghi. Una risposta HTTP valida non dimostra l’attualità scientifica o la completezza dei dati. Consulta [fonti e limiti](docs/FUENTES_SISMICAS.md).

## Installazione

Requisiti: PHP 8.2+ con `curl`, `intl`, `mbstring` e `mysqli`, Composer 2 e MySQL/MariaDB. CodeIgniter è incluso in `system/`; le dipendenze vengono installate da `composer.lock`. Le verifiche richiedono anche Node 22+, Python 3 e PHP-CGI.

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

Crea un database vuoto e configura `database.default.*` e `app.baseURL` in `.env`. Poi esegui:

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

Apri <http://127.0.0.1:8080/index.php/sismos>. Le nuove installazioni devono esporre soltanto `public/`. Segui la [procedura completa](docs/INSTALLATION.md) per chiavi e permessi.

## Configurazione e sincronizzazione

| Impostazione | Utilizzo |
|---|---|
| `CI_ENVIRONMENT` | `development` in locale; `production` sul server. |
| `app.baseURL` | URL pubblico con barra finale. |
| `APP_API_TOKEN` | Token Bearer per le rotte protette. |
| `database.default.*` | Connessione al database. |
| `encryption.key` | Chiave privata di cifratura. |
| `quakes.*` | Fonti, filtri e intervalli. |
| `sigra.version`, `sigra.build` | Sostituzioni facoltative dei metadati della versione. |

La mappa richiede aggiornamenti automaticamente. L’intervallo generale minimo è di cinque minuti; SGC usa quindici minuti per host. Cache, blocchi e pause dopo gli errori sono condivisi tra i processi. Cron è facoltativo. Per avviare una sincronizzazione normale:

```bash
php spark sigra:sync
```

Il comando rispetta gli intervalli e può salvare eventi nel database. `/acerca-de#sources-health` mostra lo stato registrato; aggiornare il pannello non contatta i fornitori. Conserva `writable/cache/providers` durante gli aggiornamenti.

## API, privacy e verifiche

Le rotte protette usano `Authorization: Bearer <APP_API_TOKEN>`. Consulta i contratti [sismi](docs/API_SISMOS.md), [rete civica](docs/API_RED.md) e [OpenAPI](docs/api/openapi.yaml). La versione del contratto rimane `v1`.

La geolocalizzazione è facoltativa. L’approssimazione tramite IP, quando usata come alternativa, dipende da `ipapi.co`. Non pubblicare token, credenziali o segnalazioni private negli issue.

```bash
composer test
composer analyse
python3 tools/check-git.py
```

I test MySQL richiedono un database temporaneo selezionato esplicitamente; non usare la produzione.

## Aggiornamento e documentazione

La versione 1.3.1.3 ripristina la compatibilità HTTP con SGC. Il test locale ha ricevuto HTTP 200 e normalizzato 100 eventi; l’utente ha poi confermato il funzionamento dopo il rilascio della correzione. Questa revisione non aggiunge migrazioni. Consulta [file da caricare e ripristino](docs/SGC_CONEXION_2026-11-19.md).

- [Gestione e recupero](docs/PRODUCCION.md)
- [Architettura](docs/architecture/README.md)
- [Versioni](docs/VERSIONES.md) e [cronologia](CHANGELOG.md)
- [Contribuire](CONTRIBUTING.md) e [codice di condotta](CODE_OF_CONDUCT.md)
- [Licenza MIT](LICENSE) e [avvisi di terze parti](THIRD_PARTY_NOTICES.md)
