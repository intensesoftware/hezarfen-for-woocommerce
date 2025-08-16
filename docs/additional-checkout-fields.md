---
post_title: Additional checkout fields
sidebar_label: Additional checkout fields
sidebar_position: 4
---

# Additional checkout fields

The Hezarfen for WooCommerce plugin now supports both **classic WooCommerce checkout** and the new **WooCommerce Checkout Block** with additional checkout fields functionality.

## Overview

For Turkish addresses, the plugin transforms the standard WooCommerce address fields to better match Turkish address conventions:

- **Address 1** → **Neighborhood** (Mahalle)
- **City** → **District** (İlçe)

This transformation only occurs when the country is set to Turkey (TR).

## Available field locations

The additional checkout fields are registered in the **address** location, which means they appear in both shipping and billing address forms.

### Address fields for Turkey

When a customer selects Turkey as their country, two additional fields become available:

| Field ID | Label | Description |
| -------- | ----- | ----------- |
| `hezarfen/district` | District | Replaces the city field with Turkish districts |
| `hezarfen/neighborhood` | Neighborhood | Replaces address_1 with Turkish neighborhoods |

## Field behavior

### Conditional visibility

The Turkish address fields use JSON Schema conditions to:

- **Show only for Turkey**: Fields are only visible when country is "TR"
- **Required for Turkey**: Fields become required when country is "TR"
- **Hidden for other countries**: Fields are hidden for all other countries

### Dynamic loading

The fields are dynamically populated based on the selected state/province:

1. When a **state** is selected, the **district** field is populated with available districts
2. When a **district** is selected, the **neighborhood** field is populated with available neighborhoods

## Implementation details

### Backend integration

The plugin registers additional checkout fields using the `woocommerce_register_additional_checkout_field` function:

```php
woocommerce_register_additional_checkout_field(
    array(
        'id'            => 'hezarfen/district',
        'label'         => __( 'District', 'hezarfen-for-woocommerce' ),
        'location'      => 'address',
        'type'          => 'select',
        'required'      => array(
            'type'       => 'object',
            'properties' => array(
                'customer' => array(
                    'properties' => array(
                        'address' => array(
                            'properties' => array(
                                'country' => array(
                                    'const' => 'TR'
                                )
                            )
                        )
                    )
                )
            )
        ),
        'hidden'        => array(
            'type'       => 'object',
            'properties' => array(
                'customer' => array(
                    'properties' => array(
                        'address' => array(
                            'properties' => array(
                                'country' => array(
                                    'not' => array(
                                        'const' => 'TR'
                                    )
                                )
                            )
                        )
                    )
                )
            )
        ),
        'options'       => $this->get_district_options(),
    )
);
```

### Field mapping

The additional fields are automatically mapped to standard WooCommerce fields:

- `hezarfen/district` → `_billing_city` / `_shipping_city`
- `hezarfen/neighborhood` → `_billing_address_1` / `_shipping_address_1`

This ensures compatibility with existing shipping methods, payment gateways, and other plugins.

### AJAX integration

The plugin provides AJAX endpoints for dynamic field updates:

- `hezarfen_get_districts_for_checkout_block` - Returns districts for a given city
- `hezarfen_get_neighborhoods_for_checkout_block` - Returns neighborhoods for a given city and district

### JavaScript integration

The frontend JavaScript handles:

- Monitoring state/province changes
- Updating district options dynamically
- Updating neighborhood options based on district selection
- Showing/hiding Turkish fields based on country selection

## Accessing field values

### Using helper methods

```php
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

$checkout_fields = Package::container()->get( CheckoutFields::class );

// Get district value
$district = $checkout_fields->get_field_from_object( 'hezarfen/district', $order, 'billing' );

// Get neighborhood value
$neighborhood = $checkout_fields->get_field_from_object( 'hezarfen/neighborhood', $order, 'billing' );
```

### Direct meta access

```php
// District is stored in the city field
$district = $order->get_billing_city(); // or get_shipping_city()

// Neighborhood is stored in the address_1 field
$neighborhood = $order->get_billing_address_1(); // or get_shipping_address_1()
```

## Backward compatibility

The plugin maintains full backward compatibility with:

- **Classic checkout**: Existing functionality continues to work
- **Existing orders**: Previously saved addresses remain accessible
- **Third-party plugins**: Standard WooCommerce field mapping ensures compatibility

## Validation and sanitization

### Validation

The plugin validates that:

- Selected districts exist for the chosen city
- Selected neighborhoods exist for the chosen city and district
- Fields are required when country is Turkey

### Sanitization

All field values are sanitized using `sanitize_text_field()` to ensure data integrity.

## Styling

The plugin includes CSS for proper styling of the checkout block fields:

- Consistent styling with WooCommerce checkout block
- Loading states for dynamic field updates
- Error states for validation failures
- Responsive design for mobile devices

## Files added

The checkout block integration adds the following files:

- `includes/Checkout_Block_Integration.php` - Main integration class
- `assets/js/checkout-block.js` - Frontend JavaScript
- `assets/css/checkout-block.css` - Styling for checkout block fields

## Requirements

- WooCommerce 8.0+
- WooCommerce Blocks plugin
- PHP 7.0+
- WordPress 5.7+

## Usage example

When a customer from Turkey goes through checkout:

1. They select "Turkey" as their country
2. The district and neighborhood fields become visible and required
3. They select their state/province (e.g., "TR34" for Istanbul)
4. The district field populates with Istanbul districts
5. They select a district (e.g., "Kadıköy")
6. The neighborhood field populates with Kadıköy neighborhoods
7. They select their neighborhood
8. The order is saved with the correct Turkish address format

This provides a much better user experience for Turkish customers while maintaining compatibility with the global WooCommerce ecosystem.