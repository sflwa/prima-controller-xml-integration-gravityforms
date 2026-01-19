=== Sync Manager for Prima Controller and Gravity Forms ===
Contributors: philip-l
Tags: prima nova, gravity forms, access control, rfid sync, automation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real-time resident synchronization between Gravity Forms submissions and Prima Access Controllers (Prima Nova).

== Description ==

Sync Manager for Prima Controller and Gravity Forms bridges the gap between resident registration and physical access control. When a resident registers via Gravity Forms, the plugin automatically looks up their address in the Prima Controller. 

Staff can then manage pending assignments through a secure dashboard or a password-protected front-end table, allowing for seamless RFID assignment without ever leaving the WordPress admin.

Key Features:
* **Address-Based Lookup:** Automatically matches GF entries to Prima resident records.
* **Session-Based Auth:** Securely connects to Prima Nova controllers using standard XML authentication.
* **Front-End Management:** Use the `[prima_pending_sync]` shortcode to let staff assign RFIDs from the front-end.
* **Activity Logging:** Three levels of logging (Disabled, Simple, Debug) to track all XML communication.
* **Entry History:** Automatically adds notes to Gravity Forms entries when a successful sync occurs.

== Installation ==

1. Upload the `sync-manager-prima-gf` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Sync Manager > Settings** and configure your Controller URL, Admin Credentials, and Form/Field IDs.
4. Run the **Initial Setup Sync** in Settings to map your existing resident records.

== Frequently Asked Questions ==

= What is the shortcode? =
Use `[prima_pending_sync]` on any page. We recommend password-protecting the page for security.

= Does this require a Prima License? =
Yes, your Prima Controller must have the XML Integration license enabled. If you see "Error 22" in logs, please contact Prima support.

= How do I find my Field IDs? =
Open your Gravity Form editor. The ID for each field is displayed in the field settings sidebar.

== Screenshots ==

1. The Sync Dashboard showing residents matched by address.
2. The Settings page for API and Field mapping.

== Changelog ==

= 1.5.0 =
* Full refactor for WordPress Coding Standards.
* Added 3-state logging levels (Disabled, Simple, Debug).
* Improved AJAX reliability and front-end search.
* Standardized slug and prefixing (SMPG).

= 1.0.0 =
* Initial release.