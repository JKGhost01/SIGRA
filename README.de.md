# SIGRA · Globale Erdbebenbeobachtung und Bürgernetzwerk

**v1.3.1.3 · SGC-Verbindung wiederhergestellt** · Build `20261119.1` · 19.11.2026

[Español](README.md) · [English](README.en.md) · [Português](README.pt.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Italiano](README.it.md) · [日本語](README.ja.md) · [简体中文](README.zh-CN.md) · [العربية](README.ar.md)

Diese Anleitung bietet einen deutschsprachigen Einstieg. Die [vollständige Dokumentation](README.md) und die technischen Anleitungen werden auf Spanisch gepflegt. Befehle, Pfade und API-Verträge behalten ihre ursprünglichen Namen. Die Weboberfläche ist auf Spanisch und Englisch verfügbar.

## Überblick

SIGRA ist eine quelloffene PHP-Anwendung. Sie führt veröffentlichte Erdbebendaten von **SGC, USGS, EMSC, GeoNet, GEOFON/GFZ und INGV** in einer durchsuchbaren Karte zusammen. Dazu kommen ein Archiv, Filter, eine JSON-API und private Meldungen aus der Bevölkerung. Ein Umkreisfilter zeigt Ereignisse in der Nähe eines gewählten Standorts.

Das Sensornetz mit Mobiltelefonen ist **experimentell**. SIGRA sagt keine Erdbeben voraus, garantiert keine Frühwarnung und ersetzt weder Behörden noch offizielle Warnsysteme. Der Android-Client wird separat unter [SIGRA_MOBILE](https://github.com/JKGhost01/SIGRA_MOBILE) entwickelt.

## Funktionen und Datenquellen

- Leaflet-Karte, Archiv, Detailansichten und Filter nach Ort, Quelle, Magnitude und Entfernung.
- SGC für Kolumbien; USGS, EMSC und GEOFON mit weltweiter, jeweils begrenzter Abdeckung.
- GeoNet für Neuseeland; INGV für Italien und angrenzende Gebiete.
- Web- und Android-API, private Meldungen sowie eine installierbare PWA.
- Vereinheitlichung und Abgleich der Quelldaten; gespeicherte Ereignisse bleiben bei Ausfällen verfügbar.

Abdeckung und Aktualisierungsintervalle unterscheiden sich. Eine gültige HTTP-Antwort belegt weder wissenschaftliche Aktualität noch Vollständigkeit. Siehe [Datenquellen und Grenzen](docs/FUENTES_SISMICAS.md).

## Installation

Voraussetzungen: PHP 8.2+ mit `curl`, `intl`, `mbstring` und `mysqli`, Composer 2 sowie MySQL/MariaDB. CodeIgniter liegt unter `system/`; Abhängigkeiten werden anhand von `composer.lock` installiert. Prüfungen benötigen zusätzlich Node 22+, Python 3 und PHP-CGI.

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

Eine leere Datenbank anlegen und `database.default.*` sowie `app.baseURL` in `.env` konfigurieren. Anschließend:

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

<http://127.0.0.1:8080/index.php/sismos> öffnen. Bei neuen Installationen darf nur `public/` öffentlich erreichbar sein. Schlüssel und Berechtigungen beschreibt die [vollständige Installationsanleitung](docs/INSTALLATION.md).

## Konfiguration und Synchronisierung

| Einstellung | Zweck |
|---|---|
| `CI_ENVIRONMENT` | Lokal `development`, auf dem Server `production`. |
| `app.baseURL` | Öffentliche URL mit abschließendem Schrägstrich. |
| `APP_API_TOKEN` | Bearer-Token für geschützte Routen. |
| `database.default.*` | Datenbankverbindung. |
| `encryption.key` | Privater Verschlüsselungsschlüssel. |
| `quakes.*` | Quellen, Filter und Intervalle. |
| `sigra.version`, `sigra.build` | Optionale Überschreibung der Versionsangaben. |

Die Karte fordert Aktualisierungen automatisch an. Allgemein gilt ein Mindestintervall von fünf Minuten, für SGC fünfzehn Minuten pro Host. Cache, Sperren und Fehlerpausen werden zwischen Prozessen geteilt. Cron ist optional. Eine normale Synchronisierung lässt sich so starten:

```bash
php spark sigra:sync
```

Der Befehl beachtet die Intervalle und kann Ereignisse in der Datenbank speichern. `/acerca-de#sources-health` zeigt den gespeicherten Zustand. Das Aktualisieren dieser Anzeige kontaktiert keine Anbieter. `writable/cache/providers` bei Bereitstellungen erhalten.

## API, Datenschutz und Prüfungen

Geschützte Routen erwarten `Authorization: Bearer <APP_API_TOKEN>`. Die Verträge für [Erdbeben](docs/API_SISMOS.md), das [Bürgernetzwerk](docs/API_RED.md) und [OpenAPI](docs/api/openapi.yaml) beschreiben die Schnittstellen. Die Vertragsversion bleibt `v1`.

Die Standortfreigabe ist freiwillig. Eine bei Bedarf verwendete ungefähre IP-Ortung nutzt `ipapi.co`. Tokens, Zugangsdaten und private Meldungen gehören nicht in öffentliche Issues.

```bash
composer test
composer analyse
python3 tools/check-git.py
```

MySQL-Tests benötigen eine ausdrücklich ausgewählte, entbehrliche Testdatenbank. Keine Produktionsdatenbank verwenden.

## Aktualisierung und weitere Dokumentation

Version 1.3.1.3 stellt die HTTP-Kompatibilität mit SGC wieder her. Der lokale Test erhielt HTTP 200 und normalisierte 100 Ereignisse. Anschließend bestätigte der Nutzer die Funktion nach Bereitstellung der Korrektur. Diese Revision enthält keine neuen Migrationen. Siehe [Bereitstellung und Rücknahme](docs/SGC_CONEXION_2026-11-19.md).

- [Betrieb und Wiederherstellung](docs/PRODUCCION.md)
- [Architektur](docs/architecture/README.md)
- [Versionen](docs/VERSIONES.md) und [Änderungsverlauf](CHANGELOG.md)
- [Mitwirken](CONTRIBUTING.md) und [Verhaltenskodex](CODE_OF_CONDUCT.md)
- [MIT-Lizenz](LICENSE) und [Hinweise zu Drittanbietern](THIRD_PARTY_NOTICES.md)
