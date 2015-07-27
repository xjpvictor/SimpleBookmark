SimpleBookmark
======

This is a simple web-based bookmark manager written in PHP

## Features ##

* Save bookmarks/folders

* Automatically get url titles

* Drag and Drop to organize bookmarks

* Bookmarklet for adding/showing bookmarks

* Email links to save to bookmarks

* Import from Google Chrome or Firefox

## Requirements and Installations ##

* PHP >= 5.5.3

* Optional IMAP extensions

* Copy `config.php-dist` to `data/config.php` and edit accordingly to setup

* All personal data is in the directory **data**. Backup is only needed for this directory.

## Usage ##

* Drag bookmarklet to bookmarks bar to enable bookmarking from web

* Add a cron job to access **parsemail.php** for email bookmarking function

* Email "[Folder name]" as subject and "link" as body to save to specific folder

* Copy or save **bookmarks.json** from Google Chrome or Firefox to _utils_, access `utils/import_chrome.php` or `utils/import_firefox.php` to import bookmarks

## License ##

This work uses MIT license. Feel free to use, modify or distribute. I'll NOT be responsible for any loss caused by this work.
