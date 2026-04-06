# Changelog

All notable changes to `atlasflow/order-bridge` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.2] - 2026-04-06

### Changed

- `FulfilmentInterface::getNotes()` now returns `NoteDto[]|null` (an array of `NoteDto` objects) instead of `?string`, aligning with API spec v1.4.2.
- `FulfilmentDto::$notes` updated to `NoteDto[]|null`.
- `OrderDto::$notes` is now a nullable array (`NoteDto[]|null`), consistent with the order-level notes field.
- `OrderSerialiser` serialises fulfilment notes through the shared `serialiseNote()` helper.
- `InboundParser` maps fulfilment notes through the shared `mapNote()` helper.
- `PayloadValidator` validates `fulfilment.notes` as an array of note objects when present.

## [1.4.1]

### Added

- Initial package scaffold.
