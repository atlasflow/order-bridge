# Changelog

All notable changes to `atlasflow/order-bridge` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0]

### Added

- Optional order-level discount voucher. `OrderInterface::getDiscountVoucher(): ?DiscountVoucherDto` (null when no voucher), `DiscountVoucherDto` (`string $code`, `string $percentage` at `DISCOUNT_SCALE`), and `OrderDto::$discountVoucher` (appended, defaults to null).
- Wire key `orders[].discount_voucher` = `{"code": "...", "percentage": "10.000000"}`, or null. The money still travels as each eligible line's `discount`; the voucher records only which one was used. Line maths and totals are unchanged.
- `PayloadValidator` checks a present voucher: `code` a non-empty string of at most 64 characters, `percentage` a decimal string above 0 and at most 100 with exactly `discount_scale` places.

### Notes

- Backward-compatible on the wire: payloads without the key parse unchanged. `SCHEMA_VERSION` stays `1.4.3`, because the validator requires an exact match and a receiver on a bumped version would be rejected by every older receiver.
- Implementers of `OrderInterface` must add `getDiscountVoucher()` (return null if unused).
- Test fixtures now follow `SchemaVersion::SCHEMA_VERSION` instead of a pinned `1.4.2`.

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
