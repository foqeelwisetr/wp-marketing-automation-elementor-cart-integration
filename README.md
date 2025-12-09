for a specific form to  capture cart 
add_filter('bwfan_elementor_cart_allowed_form_ids', function($allowed_form_ids) {
    return array('form_id_1', 'form_id_2', 123);
});

# FunnelKit Automations - Elementor Form Cart Integration

A custom WordPress plugin that captures email addresses from Elementor forms and automatically links them to abandoned carts in FunnelKit Automations (formerly Autonami). This solution is upgrade-safe and reusable.

## Features

- ✅ **Automatic Email Capture**: Extracts email addresses from Elementor form submissions
- ✅ **Contact Creation**: Creates or updates contacts in FunnelKit Automations CRM
- ✅ **Cookie Management**: Sets the `_fk_contact_uid` cookie to link forms to carts
- ✅ **Cart Linking**: Automatically links form submissions to existing abandoned carts
- ✅ **Guest Cart Support**: Works with guest carts, even if users don't reach checkout
- ✅ **Upgrade-Safe**: Custom plugin that won't be overwritten by plugin updates
- ✅ **Client-Side Fallback**: JavaScript fallback for cookie setting when headers are already sent
- ✅ **Field Mapping**: Automatically maps common form fields (first name, last name, phone)

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- FunnelKit Automations (wp-marketing-automations)
- Elementor Pro (for form submissions)

## Installation

1. Upload the `bwfan-elementor-cart-integration` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically start capturing emails from Elementor forms

## How It Works

### Server-Side Processing

1. **Form Submission Hook**: The plugin hooks into Elementor's `elementor_pro/forms/new_record` action
2. **Email Extraction**: Automatically detects email fields in forms using common patterns:
   - `email`, `your-email`, `e-mail`, `mail`, `email_address`, `billing_email`
3. **Contact Creation**: Uses FunnelKit's `bwf_get_contact()` function to create or update contacts
4. **Cookie Setting**: Sets the `_fk_contact_uid` cookie using FunnelKit's cookie management
5. **Cart Linking**: Finds existing abandoned carts by email and updates them with the contact information

### Client-Side Fallback

- JavaScript monitors form submissions and email field changes
- Provides a fallback cookie setting mechanism if server-side fails
- Works with Elementor popup forms as well

## Usage

Once activated, the plugin works automatically. No configuration needed!

### Automatic Email Detection

The plugin automatically detects email fields in Elementor forms by:
- Field type (email input type)
- Field ID patterns (email, your-email, etc.)
- Field name patterns

### Field Mapping

The plugin automatically maps common form fields:
- **First Name**: `first_name`, `firstname`, `fname`, `name`
- **Last Name**: `last_name`, `lastname`, `lname`, `surname`
- **Phone**: `phone`, `telephone`, `mobile`, `phone_number`

## Technical Details

### Hooks Used

- `elementor_pro/forms/new_record` - Main hook for form submissions
- `wp_ajax_bwfan_elementor_set_contact_cookie` - AJAX handler for client-side cookie setting
- `wp_ajax_nopriv_bwfan_elementor_set_contact_cookie` - AJAX handler for non-logged-in users

### Database Operations

- Updates `wp_bwfan_abandonedcarts` table to link carts with emails
- Uses FunnelKit's contact system for contact management
- Respects existing cart statuses (only updates active carts)

### Cookie Management

- Sets `_fk_contact_uid` cookie with 10-year expiration
- Uses FunnelKit's `BWFAN_Common::set_cookie()` when available
- Falls back to native `setcookie()` if needed
- JavaScript fallback for client-side setting

## Upgrade Safety

This plugin is designed to be upgrade-safe:

- ✅ **Separate Plugin**: Not part of FunnelKit Automations core
- ✅ **No Core Modifications**: Uses public APIs and hooks only
- ✅ **Version Independent**: Works with any version of FunnelKit Automations
- ✅ **No Database Changes**: Uses existing tables and structures

## Troubleshooting

### Emails Not Being Captured

1. **Check Dependencies**: Ensure WooCommerce, FunnelKit Automations, and Elementor Pro are active
2. **Check Form Fields**: Ensure your form has an email field with a recognizable ID/name
3. **Debug Mode**: Enable `WP_DEBUG` to see logs in `wp-content/debug.log`

### Carts Not Linking

1. **Check Cookie**: Verify `_fk_contact_uid` cookie is being set (check browser DevTools)
2. **Check Cart Status**: Only active carts (status 0 or 1) are updated
3. **Check Email Match**: Carts are linked by email address match

### Cookie Not Setting

1. **Headers Already Sent**: If server-side fails, the JavaScript fallback should handle it
2. **Check Browser Console**: Look for JavaScript errors in browser DevTools
3. **Check AJAX Response**: Verify AJAX endpoint is working (check Network tab)

## Development

### File Structure

```
bwfan-elementor-cart-integration/
├── bwfan-elementor-cart-integration.php  # Main plugin file
├── assets/
│   └── js/
│       └── frontend.js                   # Client-side JavaScript
└── README.md                             # This file
```

### Extending the Plugin

You can extend the plugin using WordPress filters:

```php
// Modify email extraction logic
add_filter('bwfan_elementor_extract_email', function($email, $fields) {
    // Custom email extraction logic
    return $email;
}, 10, 2);

// Modify contact creation
add_action('bwfan_elementor_contact_created', function($contact, $email) {
    // Custom logic after contact creation
}, 10, 2);
```

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review FunnelKit Automations documentation
3. Check Elementor Pro form documentation

## Changelog

### 1.0.0
- Initial release
- Email capture from Elementor forms
- Contact creation/update
- Cookie management
- Cart linking
- Client-side fallback

## License

This plugin is provided as-is for use with FunnelKit Automations and Elementor Pro.

## Credits

Developed for FunnelKit Automations (formerly Autonami) integration with Elementor forms.

