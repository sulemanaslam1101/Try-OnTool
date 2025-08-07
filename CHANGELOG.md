# Changelog

All notable changes to the Try-On Tool plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1] - 2025-08-07

### Fixed
- Fixed GPL license headers displaying in admin interface
- Properly wrapped PHP comment blocks in template files
- Resolved template rendering issues in admin settings page

### Changed
- Updated version number from 1.0 to 1.1
- Improved template file structure for better compatibility

### Technical
- Fixed PHP syntax in `templates/admin/settings-page.php`
- Fixed PHP syntax in `templates/frontend/modal-template.php`
- Fixed PHP syntax in `templates/frontend/button-template.php`

## [1.0] - 2025-08-01

### Added
- Initial release of Try-On Tool plugin
- WooCommerce integration for virtual try-on functionality
- Admin settings page with license validation
- Frontend try-on button and modal interface
- Wasabi S3 integration for image storage
- GDPR-compliant image handling and user consent
- Credit-based usage system
- User role and permission controls
- Automatic image cleanup for inactive users 