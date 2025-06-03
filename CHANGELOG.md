# craft-auth0-login Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0 - 2024-01-XX

### Changed

-   Updated plugin to be compatible with Craft CMS 4.x
-   Updated composer requirements to require Craft CMS ^4.0.0 instead of ^3.0.0
-   Updated plugin comments and documentation references to point to Craft 4.x
-   Updated environment variable handling in config.php to use Craft's App::env() helper
-   Added explicit type declarations (string, bool, array) to plugin properties to match Craft 4's base Plugin class requirements

### Notes

-   This is a breaking change requiring Craft CMS 4.x
-   Users upgrading from Craft 3.x should ensure their environment meets Craft 4 requirements (PHP 8.0.2+)

## 1 - 2021-08-21

### Added

-   Initial release
