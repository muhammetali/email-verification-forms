=== Email Verification Forms ===
Contributors: erbilisim
Donate link: https://fixmob.net/donate
Tags: email verification, user registration, security, woocommerce, forms
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional email verification system for WordPress and WooCommerce with secure user registration, AJAX forms, and comprehensive admin dashboard.

== Description ==

**Email Verification Forms** is a comprehensive email verification solution that seamlessly integrates with both WordPress and WooCommerce. Enhance your website's security by requiring users to verify their email addresses before accessing their accounts.

### 🚀 Key Features

**Universal Compatibility:**
* Works with standard WordPress registration
* Full WooCommerce integration (My Account, Checkout, etc.)
* Automatically detects your environment and adapts

**Smart Verification System:**
* Token-based email verification with expiration
* Rate limiting to prevent spam
* Secure password setup after email verification
* Beautiful, responsive email templates

**Modern User Experience:**
* AJAX-powered forms (no page reloads)
* Real-time password strength indicator
* Progress indicators for multi-step process
* Mobile-friendly responsive design

**Flexible Usage Options:**
* Shortcode: `[verification_form]`
* WordPress Widget support
* Gutenberg block ready
* WooCommerce My Account integration

**Admin Dashboard:**
* Complete verification management
* User verification status overview
* Email logs and statistics
* Customizable settings

**Security Features:**
* CSRF protection with nonces
* SQL injection prevention
* XSS protection
* Strong password enforcement
* IP-based rate limiting

### 🛠️ How It Works

**For WordPress Sites:**
1. User enters email address
2. Verification email sent automatically
3. User clicks verification link
4. User sets secure password
5. Account activated and ready to use

**For WooCommerce Sites:**
1. User registers via WooCommerce (My Account or Checkout)
2. Email verification required before full account access
3. User clicks verification link in email
4. User sets new secure password (optional)
5. Full WooCommerce account access granted

### 🎨 Customization

* Branded email templates with your logo
* Customizable colors and styling
* Configurable verification expiry times
* Adjustable password requirements
* Rate limiting settings

### 🌍 Translation Ready

* Full internationalization support
* Translation-ready with .pot file
* RTL language support
* Currently available in English and Turkish

### 🔧 Developer Friendly

* Well-documented code
* WordPress coding standards
* Extensive hook system
* Modular architecture
* GPL-licensed and open source

### 📊 Requirements

* WordPress 5.0+
* PHP 7.4+
* MySQL 5.6+
* Optional: WooCommerce 3.0+ for enhanced features

== Installation ==

### Automatic Installation

1. Go to WordPress Admin → Plugins → Add New
2. Search for "Email Verification Forms"
3. Click "Install Now" and then "Activate"
4. The plugin will automatically detect your environment and configure itself

### Manual Installation

1. Download the plugin zip file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose the zip file and click "Install Now"
4. Activate the plugin
5. Go to Email Verification → Settings to configure

### Configuration

**For WordPress Sites:**
* Plugin automatically replaces default registration
* Use `[verification_form]` shortcode anywhere
* Configure settings under Email Verification menu

**For WooCommerce Sites:**
* Plugin integrates with WooCommerce registration
* Adds verification step to My Account workflow
* Verification notice appears in customer dashboard

== Frequently Asked Questions ==

= Does this work with WooCommerce? =

Yes! The plugin automatically detects WooCommerce and provides full integration including My Account pages, checkout registration, and customer dashboard notifications.

= Can I customize the email templates? =

Absolutely! The plugin includes beautiful, branded email templates that use your site's logo and colors. Advanced users can override templates in their theme.

= What happens if a user doesn't verify their email? =

Unverified users can still log in but have limited access. They'll see gentle reminders to verify their email and can request new verification emails.

= Is it secure? =

Yes! The plugin implements WordPress security best practices including nonces, sanitization, rate limiting, and secure token generation.

= Can I use it with custom registration forms? =

Yes! Use the `[verification_form]` shortcode in any page or post, or add it as a widget to your sidebar.

= Does it work with membership plugins? =

The plugin follows WordPress standards and should work with most membership plugins. For specific compatibility questions, please ask in the support forum.

= What about GDPR compliance? =

The plugin stores minimal user data (email, verification status) and includes data cleanup options. Review your specific GDPR requirements with your legal team.

= Can I translate the plugin? =

Yes! The plugin is fully translation-ready with .pot files included. Contributions for additional languages are welcome.

== Screenshots ==

1. Beautiful email verification form with progress indicators
2. Professional email template with your branding
3. WooCommerce My Account integration
4. Comprehensive admin dashboard
5. User verification management
6. Plugin settings panel
7. Password setup page with strength meter
8. Email logs and statistics

== Changelog ==

= 1.0.0 =
* Initial release
* WordPress and WooCommerce compatibility
* Email verification system
* AJAX-powered forms
* Admin dashboard
* Translation support
* Security features
* Responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release of Email Verification Forms. Install now to enhance your site's security with professional email verification.

== Support ==

For support, feature requests, or bug reports, please visit:

* **Plugin Support:** [WordPress.org Support Forum](https://wordpress.org/support/plugin/email-verification-forms/)
* **Documentation:** [Plugin Documentation](https://fixmob.net/docs/email-verification-forms/)
* **Feature Requests:** [GitHub Issues](https://github.com/muhammetali/email-verification-forms/issues)

== Contributing ==

This plugin is open source and welcomes contributions:

* **GitHub Repository:** [https://github.com/muhammetali/email-verification-forms](https://github.com/muhammetali/email-verification-forms)
* **Translate:** Help translate the plugin at [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/email-verification-forms)

== Privacy Policy ==

Email Verification Forms takes privacy seriously:

* **Data Collected:** Email addresses, verification timestamps, IP addresses for rate limiting
* **Data Usage:** Solely for email verification functionality
* **Data Retention:** Configurable cleanup options available
* **Third Parties:** No data shared with external services

For detailed privacy information, see our [Privacy Policy](https://fixmob.net/privacy).

== License ==

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.