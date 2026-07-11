=== ML App Base Core ===
Contributors: mlopesdesign
Tags: mobile app, rest api, firebase, fcm, notifications, menu, events, news, gallery
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Base WordPress backend for reusable native mobile apps.

== Description ==
ML App Base Core consolidates the best engines from the tested app stack:

* App Core engine: authentication, users, devices, FCM push, internal notifications, favorites, menu/cardapio and admin logs.
* Content API engine: home, news, events, banners, photo albums, institutional data and configurable sections.
* Professional admin pattern inspired by ML Lopes Design plugins: organized screens, cleaner cards, media upload support, push debugger and technical logs.

REST namespace: /wp-json/ml-app-base/v1

== Installation ==
Upload the ZIP as a normal WordPress plugin and activate it.

== Changelog ==

= 1.1.0 =
* Adicionado Updater self-hosted em `includes/Core/Updater.php` (padrao ml-css-pro / ml-gallery-pro).
* Auto-update via GitHub Releases nativo: `pre_set_site_transient_update_plugins` + `plugins_api` + `upgrader_post_install`.
* Source completo commitado em `mlopesdesign/ml-app-base-core` no GitHub.
* Hooks: tag `v1.1.0` e release `v1.1.0` com asset `ml-app-base-core-v1.1.0.zip`.

= 1.0.0 =
Initial generic base plugin with merged app, push, notification, menu and content engines.
