# Solspace Freeform Payments Changelog

## 1.0.0-beta.5 - 2018-11-20
### Changed
- Updated Stripe settings to allow both LIVE and TEST keys to allow for easy switching into test mode.
- Improved built in error handling on credit card fields in forms.

### Fixed
- Fixed a bug where Payments would send a 400 error on subsequent recurring payment transactions.

## 1.0.0-beta.4 - 2018-11-12
### Changed
- Updated Freeform to detect if more than 1 form is loading Payments-enabled forms on the same page, and only load Stripe JS once.

## 1.0.0-beta.3 - 2018-11-06
### Fixed
- Fixed a bug where Payments would sometimes error when using dynamic subscription-based payments.

## 1.0.0-beta.2 - 2018-09-14
### Fixed
- Fixed a bug where Payments Credit Card field was not showing up correctly in Composer and erroring in front end templates.
- Fixed a bug where not all currencies were available to use.

## 1.0.0-beta.1 - 2018-09-13
### Added
- Initial release.
