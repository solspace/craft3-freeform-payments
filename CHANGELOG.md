# Solspace Freeform Payments Changelog

## 1.0.6 - 2019-06-19
### Fixed
- Fixed a bug where using Radio field type with Freeform Payments forms (for amount) were not working correctly.

## 1.0.5 - 2019-05-07
### Changed
- Updated Payments to prepare for future official Editions support in Freeform.

## 1.0.4 - 2019-04-03
### Fixed
- Fixed a bug where Postgres users would get an error when installing the plugin. The `resourceId` column was set to binary, but should not have been.

## 1.0.3 - 2018-12-18
### Fixed
- Fixed a bug where Freeform Payments was logging credit card decline/fail errors to the Freeform error log.

## 1.0.2 - 2018-12-11
### Fixed
- Fixed a bug where Freeform Payments subscription transactions that failed would not contain any (failed) Payment information attached to the submission.

## 1.0.1 - 2018-12-07
### Fixed
- Fixed a bug where payments were not going through correctly when using with Built-in AJAX feature.

## 1.0.0 - 2018-11-27
### Fixed
- Fixed a bug where errors were being incorrectly logged to Freeform error log upon successful subscription creation.

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
