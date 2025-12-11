[unreleased]

#### 1.1.0 / 2025-11-02

* Workflows: When detecting POT changes, ignore the creation date. by @costdev in #274
* Add a Playground link to a PR. by @costdev in #278
* Banner defaults should be empty string, not null by @afragen in #280
* Fix icon dimension check to match protocol requirements by @meszarosrob in #282
* Add WP-CLI support by @costdev in #277
* [browserslist] Update browser regex by @github-actions[bot] in #275
* Generate POT - 2025-10-04 by @github-actions[bot] in #285
* Add more data to get_package_data(), renamed from get_update_data() by @afragen in #286
* Generate POT - 2025-10-09 by @github-actions[bot] in #289
* [browserslist] Update browser regex by @github-actions[bot] in #298
* Add last_updated to metadata document by @costdev in #262
* Add plugin banner & icon assets by @joedolson in #306
* Skip avatar URLs or link markup that do not contain secure.gravatar.com. by @costdev in #302
* Modify Add Plugins message. by @afragen in #305
* fix duplicate entries in featured tab by @afragen in #307
* Fix plugin search, broke with escaping by @afragen in #309
* [browserslist] Update browser regex by @github-actions[bot] in #312
* Sort plugin modal tabs by @afragen in #310
* Patch update browsers bin by @ramonfincken in #44
* [bump-version] Bump version to 1.1.0 by @github-actions[bot] in #313
* Generate POT - 2025-10-27 by @github-actions[bot] in #303

#### 1.0.0 / 2025-09-23

* [browserslist] Update browser regex by @github-actions[bot] in #209
* Add release asset header by @afragen in #211
* [browserslist] Update browser regex by @github-actions[bot] in #214
* Add defaults for missing data by @afragen in #220
* Use wp_cache_* instead of get|set_transient by @afragen in #218
* Fix dashboard news widget and add caching by @afragen in #223
* Generate POT - 2025-08-17 by @github-actions[bot] in #224
* [browserslist] Update browser regex by @github-actions[bot] in #226
* Use local metadata when attempting to get Mini-FAIR data from same site as package is registered by * @afragen in #221
* Remove primary dashboard widget news feed by @afragen in #229
* Increase planets items by @afragen in #230
* Icons: Guard against a non-array updates response by @afragen in #244
* Prevent pings of unpublished URLs. by @peterwilsoncc in #246
* Change from wp_cache_* to *_transient for fair-plugin by @afragen in #248
* [browserslist] Update browser regex by @github-actions[bot] in #232
* Use bridged data from legacy endpoints by @rmccue in #250
* Add the "Activate" button to get_action_button(). by @costdev in #251
* Check if the package is installed before trying to set its filepath and slug. by @costdev in #253
* [browserslist] Update browser regex by @github-actions[bot] in #254
* Update class-lite.php to use wp_remote_get by @afragen in #256
* Verify signatures when downloading packages by @costdev in #247
* Add package domain validation by @rmccue in #243
* Ensure the user is allowed to activate the plugin. by @costdev in #260
* Remove debug message upon successful signature verification. by @costdev in #259
* Rename package from fair to fairpm by @rmccue in #263
* Only support PLC DIDs for now. by @costdev in #261
* Allow searching by DID by @costdev in #258
* Add some contact info to composer.json by @philipjohn in #119
* [bump-version] Bump version to 1.0.0 by @github-actions[bot] in #264
* Generate POT - 2025-09-23 by @github-actions[bot] in #265

#### 0.4.1 / 2025-07-31

* Update plugin.php version 0.3.0 -> 0.4.0 by @handpressed in #202
* Add workflow to bump the version by @rmccue in #203
* [bump-version] Bump version to 0.4.1 by @github-actions[bot] in #206
* Fix header version bump by @rmccue in #207

#### 0.4.0 / 2025-07-29

* un-escape class-lite.php for Ryan by @afragen in #121
* Add random color SVG icons for updates by @afragen in #120
* Add some phpcs fixes to FAIR/Icons by @afragen in #133
* Add and apply coding standards. by @costdev in #129
* Use const in icons/namespace.php by @afragen in #134
* add Git Updater Lite based updater for packages by @afragen in #131
* Declare the plugin as network-only by @johnbillion in #135
* Updated italian translation by @andreg in #118
* Remove the main constraint when running the PHPCS workflow. by @costdev in #140
* Update CONTRIBUTING.md by @jorydotcom in #146
* Refactor for reuse of functions elsewhere by @afragen in #150
* add check to update transient for default icons check by @afragen in #149
* [browserslist] Update browser regex by @github-actions[bot] in #141
* Only exclude class-lite.php from PHPCS by @afragen in #157
* Ping IndexNow for newly 404ing URLs. by @peterwilsoncc in #160
* Add WP Core compat.php by @afragen in #164
* Make compat.php compliant with the coding standard. by @costdev in #165
* refactoring avatar settings by @norcross in #123
* Fix code standards issues by @afragen in #171
* Replace strpos() with newer str_*() functions. by @costdev in #166
* Split compat.php into php-polyfill.php and wp-polyfill.php by @afragen in #170
* Add installer for FAIR protocol by @rmccue in #71
* Remove unused use statement in FAIR\Updater. by @costdev in #194
* Fix an inconsistent error code in parse_did(). by @costdev in #191
* Generate POT - 2025-07-28 by @github-actions[bot] in #195
* Fix context message for platform string. by @joedolson in #198
* Remove Packages, Updater from feature flag by @afragen in #197
* Generate POT - 2025-07-29 by @github-actions[bot] in #200
* Fix the documented type and default value of MetadataDocument::$sections. by @costdev in #193
* Ensure the returned MetadataDocument ID matches the DID in the request. by @costdev in #192
