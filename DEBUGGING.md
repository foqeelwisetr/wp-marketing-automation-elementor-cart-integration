# Debugging Guide - Elementor Form Cart Integration

## How to View Logs

The plugin uses `BWFAN_Common::log_test_data()` to log all operations. Here's how to view them:

### Method 1: FunnelKit Test Data (Recommended)

1. Go to **FunnelKit Automations > Settings > Test Data** (or similar)
2. Look for log entries with these keys:
   - `elementor_form_hook_fired` - Confirms the hook is firing
   - `elementor_record_debug` - Shows record object structure
   - `elementor_fields_extraction` - Shows how fields are extracted
   - `elementor_fields_structure` - Shows the raw field structure
   - `elementor_field_check` - Shows each field being checked
   - `elementor_email_extraction` - Shows extracted email
   - `elementor_email_found` - Confirms email was found
   - `elementor_email_error` - Shows email extraction errors
   - `elementor_contact_creation` - Shows contact creation status
   - `elementor_contact_error` - Shows contact creation errors
   - `elementor_cookie_setting` - Shows cookie setting status
   - `elementor_cart_search` - Shows cart search results
   - `elementor_cart_linking` - Shows cart linking results
   - `elementor_form_submission_success` - Final success log

### Method 2: WordPress Debug Log

If `WP_DEBUG` is enabled, logs will also appear in:
- `wp-content/debug.log`

Enable debug mode in `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Method 3: Browser Console

Check browser console (F12) for JavaScript errors or AJAX responses.

## Testing Steps

1. **Submit an Elementor form** with an email field
2. **Check logs immediately** after submission
3. **Look for these key indicators**:
   - ✅ `elementor_form_hook_fired` = Hook is working
   - ✅ `elementor_email_found` = Email was extracted
   - ✅ `elementor_contact_creation` with `contact_created: true` = Contact created
   - ✅ `elementor_cookie_setting` with `cookie_set: true` = Cookie set
   - ✅ `elementor_cart_linking` with `carts_linked > 0` = Carts linked

## Common Issues & Solutions

### Issue: Hook Not Firing
**Symptoms**: No `elementor_form_hook_fired` log entry

**Possible Causes**:
- Elementor Pro not active
- Hook priority conflict
- Form not using Elementor Pro forms

**Solutions**:
- Verify Elementor Pro is active
- Check form is Elementor Pro form (not basic HTML form)
- Try changing hook priority in code

### Issue: Fields Not Extracted
**Symptoms**: `elementor_fields_extraction` shows empty or `fields_count: 0`

**Possible Causes**:
- Record object structure different than expected
- Fields accessed incorrectly

**Solutions**:
- Check `elementor_record_debug` log for available methods
- Review `elementor_fields_structure` to see actual structure
- May need to adjust field extraction logic

### Issue: Email Not Found
**Symptoms**: `elementor_email_not_found` log entry

**Possible Causes**:
- Email field ID/name doesn't match patterns
- Email field type not detected
- Field value structure different

**Solutions**:
- Check `elementor_field_check` logs to see field IDs/names
- Add your field ID/name to `$email_field_patterns` array
- Review `elementor_fields_structure` to see actual field structure

### Issue: Contact Not Created
**Symptoms**: `elementor_contact_error` log entry

**Possible Causes**:
- `bwf_get_contact()` function not available
- Contact creation failed
- Invalid email format

**Solutions**:
- Verify FunnelKit Automations is active
- Check email is valid format
- Review `elementor_contact_creation` log for details

### Issue: Cookie Not Set
**Symptoms**: `elementor_cookie_setting` shows `cookie_set: false`

**Possible Causes**:
- Headers already sent
- Cookie domain/path issues
- `BWFAN_Common::set_cookie()` not available

**Solutions**:
- Check `headers_sent` status in log
- JavaScript fallback should handle this
- Verify cookie in browser DevTools > Application > Cookies

### Issue: Carts Not Linked
**Symptoms**: `elementor_cart_linking` shows `carts_linked: 0`

**Possible Causes**:
- No abandoned carts exist for email
- Cart status not active (0 or 1)
- Email mismatch

**Solutions**:
- Check `elementor_cart_search` log for found carts
- Verify cart exists in FunnelKit dashboard
- Check cart status is active
- Ensure email matches exactly

## Log Entry Examples

### Successful Submission
```json
{
  "hook_fired": true,
  "timestamp": "2024-01-15 10:30:00"
}

{
  "extracted_email": "user@example.com",
  "is_valid_email": true
}

{
  "contact_created": true,
  "contact_id": 123,
  "contact_uid": "abc123def456"
}

{
  "cookie_set": true,
  "contact_uid": "abc123def456"
}

{
  "carts_linked": 1,
  "email": "user@example.com"
}

{
  "success": true,
  "email": "user@example.com",
  "contact_id": 123,
  "carts_linked": 1
}
```

### Failed Submission (Email Not Found)
```json
{
  "hook_fired": true,
  "timestamp": "2024-01-15 10:30:00"
}

{
  "fields_structure": [...],
  "fields_count": 3
}

{
  "field_key": 0,
  "field_id": "name",
  "field_type": "text",
  "field_value": "John Doe",
  "is_email": false
}

{
  "error": "No email found in fields"
}
```

## Next Steps After Debugging

1. **If hook not firing**: Check Elementor Pro activation and form type
2. **If fields not extracted**: Review field structure and adjust extraction logic
3. **If email not found**: Add field ID/name to patterns or adjust detection
4. **If contact not created**: Check FunnelKit Automations is active
5. **If cookie not set**: Check headers and use JavaScript fallback
6. **If carts not linked**: Verify carts exist and email matches

## Getting Help

When reporting issues, include:
- All relevant log entries (copy from Test Data)
- Elementor form field IDs/names
- FunnelKit Automations version
- Elementor Pro version
- WordPress version

