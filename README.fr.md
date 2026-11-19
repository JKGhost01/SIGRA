# SIGRA · Suivi sismique mondial et réseau citoyen

**v1.4.0-beta.1 · Globe 3D expérimental · BETA** · `20261119.beta1` · 2026-11-19

> Bêta de test avec CesiumJS, carte 2D de secours et catalogue SGC complet. La version stable reste 1.3.1.3. [1.4.0-beta.1](docs/BETA_1.4.0.md)

[Español](README.md) · [English](README.en.md) · [Português](README.pt.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Italiano](README.it.md) · [日本語](README.ja.md) · [简体中文](README.zh-CN.md) · [العربية](README.ar.md)

Ce guide présente le projet et son installation en français. La [documentation complète](README.md) et les guides techniques sont maintenus en espagnol. Les commandes, chemins et contrats d’API conservent leurs noms d’origine. L’interface web est disponible en espagnol et en anglais.

## Présentation

SIGRA est une application PHP libre qui rassemble les séismes publiés par **SGC, USGS, EMSC, GeoNet, GEOFON/GFZ et INGV** dans une carte consultable, avec historique, filtres, API JSON et signalements citoyens privés. Le filtre de proximité permet de consulter les événements autour d’une position choisie.

Le réseau de capteurs téléphoniques est **expérimental**. SIGRA ne prédit pas les séismes, ne garantit pas d’alerte précoce et ne remplace ni les autorités ni les systèmes officiels. Le client Android est développé séparément dans [SIGRA_MOBILE](https://github.com/JKGhost01/SIGRA_MOBILE).

## Fonctionnalités et sources

- Carte Leaflet, historique, fiches détaillées et filtres par lieu, source, magnitude et distance.
- SGC pour la Colombie ; USGS, EMSC et GEOFON pour une couverture mondiale avec des limites propres à chaque catalogue.
- GeoNet pour la Nouvelle-Zélande ; INGV pour l’Italie et ses environs.
- API web et Android, signalements privés et PWA installable.
- Normalisation et rapprochement des observations ; conservation du catalogue enregistré lorsqu’une source échoue.

Les couvertures et fréquences diffèrent selon les sources. Une réponse HTTP valide ne garantit ni l’actualité scientifique ni l’exhaustivité du catalogue. Voir [les sources et leurs limites](docs/FUENTES_SISMICAS.md).

## Installation

Prérequis : PHP 8.2+ avec `curl`, `intl`, `mbstring` et `mysqli`, Composer 2 et MySQL/MariaDB. CodeIgniter est inclus dans `system/` ; les dépendances sont installées depuis `composer.lock`. Node 22+, Python 3 et PHP-CGI servent aux vérifications.

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

Créez une base vide et renseignez `database.default.*` et `app.baseURL` dans `.env`. Ensuite :

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

Ouvrez <http://127.0.0.1:8080/index.php/sismos>. Les nouvelles installations doivent exposer uniquement `public/`. Suivez le [guide d’installation complet](docs/INSTALLATION.md) pour les clés et les permissions.

## Configuration et synchronisation

| Paramètre | Utilisation |
|---|---|
| `CI_ENVIRONMENT` | `development` en local ; `production` sur le serveur. |
| `app.baseURL` | URL publique terminée par une barre oblique. |
| `APP_API_TOKEN` | Jeton Bearer des routes protégées. |
| `database.default.*` | Connexion à la base de données. |
| `encryption.key` | Clé privée de chiffrement. |
| `quakes.*` | Sources, filtres et intervalles. |
| `sigra.version`, `sigra.build` | Remplacement facultatif des métadonnées de version. |

La carte demande automatiquement des mises à jour. L’intervalle général minimal est de cinq minutes ; SGC impose quinze minutes par hôte. Cache, verrous et pauses après erreur sont partagés entre processus. Cron reste facultatif. Pour lancer une synchronisation normale :

```bash
php spark sigra:sync
```

Cette commande respecte les intervalles et peut enregistrer des événements en base. `/acerca-de#sources-health` affiche l’état enregistré ; son bouton d’actualisation ne contacte pas les fournisseurs. Conservez `writable/cache/providers` lors des déploiements.

## API, confidentialité et vérifications

Les routes protégées utilisent `Authorization: Bearer <APP_API_TOKEN>`. Consultez les contrats [séismes](docs/API_SISMOS.md), [réseau citoyen](docs/API_RED.md) et [OpenAPI](docs/api/openapi.yaml). La version du contrat reste `v1`.

La géolocalisation est facultative. L’approximation par IP, si elle est utilisée en secours, dépend de `ipapi.co`. Ne publiez pas de jetons, d’identifiants secrets ou de signalements privés dans les issues.

```bash
composer test
composer analyse
python3 tools/check-git.py
```

Les tests MySQL exigent une base jetable sélectionnée explicitement, jamais la production.

## Mise à jour et documentation

La version 1.3.1.3 rétablit la compatibilité HTTP avec SGC. Le test local a reçu HTTP 200 et normalisé 100 événements ; l’utilisateur a confirmé le fonctionnement après déploiement du correctif. Cette révision n’ajoute aucune migration. Consultez [les fichiers à déployer et la procédure de retour arrière](docs/SGC_CONEXION_2026-11-19.md).

- [Exploitation et récupération](docs/PRODUCCION.md)
- [Architecture](docs/architecture/README.md)
- [Versions](docs/VERSIONES.md) et [historique](CHANGELOG.md)
- [Contribuer](CONTRIBUTING.md) et [code de conduite](CODE_OF_CONDUCT.md)
- [Licence MIT](LICENSE) et [mentions de tiers](THIRD_PARTY_NOTICES.md)
