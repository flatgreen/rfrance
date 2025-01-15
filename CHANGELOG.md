# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),

## [Unreleased]
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

