# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
try to follow [Semantic Versioning](https://semver.org/)

## [Unreleased]

## 2.6.1 - 202606-05 Little bug fix
### Fixed
- Les "encarts" n'étaient plus détectés, c'est réglé ! (changement de type de la page ?)
- maj des tests avec le nouveau site de rf

## 2.6.0 - 2026-04-19
### Fixed
- le site de RF change, cette classe aussi, gros refactoring
- les images sont, à priori, toutes bien retrouvées
- les url sont nettoyées en entrée, c'est mieux !
### Changed
- le choix des medias (audios) est maintenant celui de RF, la préférence interne est mise en stand-by.
- il y a plus de requêtes vers RF, c'est comme ça, faudrait paralléliser !
- maj de composer.json pour les dépendances
### Deprecated
- $this->error sera enlevée à la prochaine version, il ne restera que les exceptions

## 2.5.0 - 2025-07-29
### Fixed
- erreur sur certaines émissions (changement dans l'API ?)
- la classe peut maintenant être instantiée qu'une seule fois et faire +sieurs 'extract()' sans erreur.
### Changed
- BC, on ne peut plus ajouter un crawler, mais directement le html avant le 'extract()'.
### Removed
- Fin de l'utilisation des dépendances à Symfony/dom-crawler et Symfony/css-selector
### Added
- beaucoup plus de tests (episode (avec et ss encart), emission, serie, rss)

## 2.4.7 - 2025-07-17
### Fixed
- Si un Crawler est existe, vérifie que l'url de l'extraction est celle du crawler
- maj test (maj de v2.4.6)

## 2.4.6 - 2025-06-08
### Fixed
- retrouve une bonne url (new API) pour l'image d'un item d'une série
- il manquait 'image' dans le retour en json

## 2.4.5 - 2025-03-24
### Added
- Les "encarts" ont toutes les informations pour être dans un flux rss
- meilleur "page" pour le type "NewsArticle"
- un peu du readme
### Changed
- php >=8.0 à >=8.1


## 2.4.4 - 2025-03-24
### Fixed
- les "encarts" peuvent être extraits dans des pages sans emission
### Added
- les pages d'article seule renvoie des informations

## 2.4.3 - 2025-02-28
### Changed
- helper avec *.info.json revu pour 'playlist' et 'channel'
### Added
- extrait les "encarts" si présents dans la page d'un "RadioEpisode", intégrés dans "all_items".

## 2.4.2 - 2025-02-18
## Fixed
- pas de html si le crawler est fourni

## 2.4.1 - 2025-02-13
### Changed
- *.info.json 'playlist' pour 'channel' contient le nom de l'émission

## 2.4 - 2025-02-08
### Changed
- Item (et Page) est un simple DTO
- rfrance::toInfoJson() est plus proche des *.info.json
- Item::playlist est remplacé par Item::emission

### Add
- test sur une serie (que le premier épisode)
- ajout du helper rfrance::toArray()

## 2.3 - 2025-02-02
No BC
### Add
- extrait tous les média disponibles, même pour une seule émission
- `Best` url explain in readme
### Change
- "Page" est maintenant seulement un DTO

## 2.2.2 - 2025-01-31
No BC
### Fixed
- Erreur si pas de date de publication dans la page web (série)
### Add
- throw une exception avant de scraper si l'url est invalide
- tests invalid urls
- tests un épisode
- tests petite série (PodcastSeries)

## 2.2.1 - 2025-01-15
### Fixed
- README.md

## 2.2 - 2025-01-15
### Fixed
- 'emission' n'était pas bien détecté dans les séries et levait une exception (chgmnt api RF)

## 2.1 - 2024-01-01
### Changed
- la méthode 'extract' a deux arguments : URL et max_items.
L'option 'force_rss' n'existe plus et c'est tant mieux, c'était confus.
- Beaucoup moins de requêtes http (une requête par page avec tous ces épisodes).

### Fixed
- les séries (ou podcast) sans lien d'épisode sont maintenant bien récupérées.

### Remove
- la dépendance à 'http-client' et à 'cUrl'.

### Add
- la 'Page' et les 'Item' contiennent un peu plus d'information.
- s'il y a plusieurs audios pour un épisode, le "meilleur" est sélectionné et la liste est accessible dans item->media.
- dependance : [colinodell/json5](https://github.com/colinodell/json5)

## 1.3.4 - 2024-11-09
### Fixed
- bug avec le crawler (filter) version 6.0
### Added
- requirement php 8.0.2
- 'force_rss' est dans les données retournées (mais pas dans le flux rss)
- on peut accéder à l'url d'un hypothétique flux rss : ->page->rss_url

## 1.3.2 - 2024-11-04
### Added
- on peut injecter un (dom) crawler dans la classe

## 1.3.1 - 2024-11-03
### Fixed
- Extraction de l'image de la page pour une série


## 1.3 - 2024-09-27
### Added
- pour les séries, extrait le "bon" titre dans la page d'une émission

### Changed
- retour à php >= 8.1

## [1.2.0] - 2024-07-08

### Added
- "http-client" (et les requêtes en parallèle avec cUrl) pour les items.
- Extrait les items sans media
- Purger le cache 

### Fixed
- les durées des caches par defaut sont plus cohérentes.
- qq légers bug :-)

## [1.1.0] - 2024-07-01

### Added

- public accès à 'short_path', partie finale de l'url.
- cache sans limite pour les items seuls

### Fixed

- Amélioration de la détection d'un flux xml (et donc de l'utilisation de 'force_rss').

### Changed

- Item::title commence par son numéro dans la série


## [1.0.0] - 2024-06-30

Première release

