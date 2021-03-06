SimpleBookmark
======

This is a simple web-based bookmark manager written in PHP

## Features ##

* Save bookmarks/folders

* Automatically get url titles

* Automatically check url accessibility

* Show preview of image urls

* Allow caching image, text, pdf and html page (in readability mode)

* Drag and Drop to organize bookmarks

* Search for bookmarks

* Bookmarklet for adding/showing bookmarks

* Email links to save to bookmarks

* Import from Google Chrome or Firefox

* URL Sync

   Prefix `bookmark_url/?u=` to url to be synced

   URLs will be deleted once clicked

## Requirements and Installations ##

* PHP >= 5.5.3

* Optional IMAP, GD, TIDY extensions

* Copy `config.php-dist` to `data/config.php` and edit accordingly to setup

* All personal data is in the directory **data**. Backup is only needed for this directory.

* **/cache**, **/data** and **/session** directories have to be writable to **http** user

## Usage ##

* Drag bookmarklet to bookmarks bar to enable bookmarking from web

* Add a cron job to access **parsemail.php** for email bookmarking function

* Email "[Folder name]" without quote and brackets as subject and "link" as body to save to specific folder (Only works for top-level folders)

* Import **bookmarks.json** from Google Chrome or Firefox to import bookmarks

## License ##

This work uses MIT license. Feel free to use, modify or distribute. I'll NOT be responsible for any loss caused by this work.
