# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),

## [Unreleased]

## [1.3.2] - 2024-11-04
### Added
- on peut injecter un (dom) crawler dans la classe

## [1.3.1] - 2024-11-03
### Fixed
- Extraction de l'image de la page pour une série


## [1.3] - 2024-09-27
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

