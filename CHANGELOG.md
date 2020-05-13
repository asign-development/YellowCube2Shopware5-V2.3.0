# Changelog
All notable changes to this project will be documented in this file.

## v2.3.0
- Compatible with SW 5.6.4
- Don't overwrite the shopware shop stock with Yellocube stock
- Added columns: Ycube Artnum, ArticleNr, EAN, Stock, YC Stock
- Sort by columns is possible
- Search by columns is possible
- Results page shows 50 results per page, if more are available then pagination is available too
- Transfer of YC stock to the shop
- Stock remains unchanged until carried out by Yellowcube
- Changes in WAB Name Mapping. Field Name4 doest exist anymore

## v2.2.2
- Don't export multilang article titles from inactive shops
- Don't export empty article titles from translations

## v2.2.1
- Insert article: fix multi translations

## v2.2.0
- Add infoline in orders
- Add article attribute-flag for YC export
- Add config for inventory reset
- Skip multiple inventory rows on update (performance/refactoring)

## v2.1.8
- Fix multiple shop bug

## v2.1.7
- Remove backend config "use certificate"
- Fix some code definitions for PHP 7 support

## v2.1.6
- Fix track&trace numbers for orders

## v2.1.5
- Ignore/skip vouchers when transferring to YellowCube
- Set last order state to "complete" if WAR response is success

## v2.1.4
- Validate max field length in order address data
- Run Inventory cronjob only once a day
- Rename Cronjobs

## v2.1.3
- Fix article bulk send & response message in backend

## v2.1.2
- Bugfix backend order buttons and actions

## v2.1.1
- Cleanup and simplify code

