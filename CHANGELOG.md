# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),

## [Unreleased]
## 2.0 - 2024-12-31
### Changed
- L'initialisation est changée. URL est obligatoire. En option : DomCrawler, le dossier et ttl du cache.
La validation de l'URL est réalisée dans le constructeur.
- la méthode 'extract' a un seul argument : max_items.

### Fixed
- les séries (ou podcast) sans lien d'épisode sont maintenant bien récupérées.

### Remove
- la dépendance à 'http-client' et à 'cUrl'.
- option 'force_rss' n'existe plus et c'est tant mieux, c'était confus.

### Add
- la 'Page' et les 'Item' contiennent un peu plus d'information.
- s'il y a plusieurs audios pour un épisode, le "meilleur" est sélectionné et la liste est accessible dans item->media.

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

