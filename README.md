# WordPress Online Ordering System

A custom WordPress plugin that integrates a React-based restaurant ordering system with WooCommerce, providing a complete online ordering solution for pizza restaurants.

## Overview

This plugin bridges a modern React single-page application with WooCommerce's e-commerce functionality, creating a seamless ordering experience that rivals third-party delivery platforms while giving restaurant owners complete control over their online ordering process.

## Features

### Customer-Facing Features
- **Interactive Menu Browsing**: Category-based navigation with search functionality
- **Advanced Customization**: Size selection, crust types, and topping management with dynamic pricing
- **Order Types**: Support for both delivery and pickup orders
- **Smart Delivery Fees**: Conditional $1.99 fee for orders under $30
- **Mobile-Optimized**: Responsive design with touch-friendly interface
- **Real-Time Updates**: Instant cart calculations and item updates

### Technical Features
- **React SPA Integration**: Modern frontend with optimized performance
- **WooCommerce Integration**: Seamless cart synchronization via AJAX
- **Custom Pricing Engine**: Dynamic calculations based on customizations
- **Secure Communication**: Nonce-based AJAX requests for security
- **Order Meta Data**: Preserves customization details through checkout
- **Shipping Logic**: Automatic shipping method filtering based on order type

## Installation

1. **Upload Plugin**
   ```bash
   # Upload to your WordPress plugins directory
   wp-content/plugins/wp-online-order/
   ```

2. **Activate Plugin**
   - Navigate to WordPress Admin → Plugins
   - Find "Slice Haven Menu Integration" and click Activate

3. **Configure WooCommerce**
   - Ensure WooCommerce is installed and activated
   - Set up your shipping zones:
     - Local Pickup for pickup orders
     - Flat Rate (ID: 2) for delivery orders

4. **Add Products**
   - Create WooCommerce products for each menu item
   - Note the product IDs for React app configuration

5. **Insert Menu Shortcode**
   ```
   [slice_haven_menu]
   ```
   Add this shortcode to any page where you want the ordering system to appear.

## Configuration

### Updating React Build Files

When you rebuild the React app, update the filenames in `slice-haven-menu.php`:

```php
// Lines 30-31 in slice-haven-menu.php
$main_js_file = 'index-DlayTtaA.js';    // Update with new hash
$main_css_file = 'index-BKjPj0-G.css';  // Update with new hash
```

### Delivery Fee Settings

Modify delivery fee logic in `slice-haven-menu.php`:

```php
// Lines 251-253
$minimum_order_amount = 30.00;        // Minimum for free delivery
$delivery_fee = 1.99;                 // Fee amount
$delivery_method_instance_id = 2;     // Your delivery method ID
```

## Project Structure

```
wp-online-order/
├── slice-haven-menu.php          # Main plugin file
├── assets/
│   └── css/
│       └── woocommerce-styles.css # WooCommerce theme overrides
├── react-app/
│   ├── index-[hash].js           # Compiled React application
│   ├── index-[hash].css          # Compiled styles
│   └── assets/                   # Product images
│       ├── pizzas/
│       ├── appetizers/
│       ├── beverages/
│       └── salads/
└── README.md                     # This file
```

## Development

### React App Development

The React application should be developed separately and built files copied to the `react-app/` directory. The app expects the following global object:

```javascript
window.sliceHavenData = {
    ajax_url: '',        // WordPress AJAX URL
    nonce: '',           // Security nonce
    my_account_url: ''   // WooCommerce account page
}
```

### Adding Menu Items

1. Create product in WooCommerce
2. Add product image
3. Update React app with product ID mapping
4. Add corresponding image to `react-app/assets/`

### Custom Hooks

The plugin provides several WordPress hooks for customization:

- `woocommerce_add_cart_item_data` - Modify cart item data
- `woocommerce_before_calculate_totals` - Adjust pricing
- `woocommerce_get_item_data` - Display customizations
- `woocommerce_checkout_create_order_line_item` - Save order meta

## API Reference

### AJAX Endpoints

**Add to Cart**
- Action: `add_react_cart_to_wc`
- Method: POST
- Parameters:
  - `cart_data`: JSON string of cart items
  - `order_type`: 'delivery' or 'pickup'
  - `_ajax_nonce`: Security nonce

### Cart Item Structure

```json
{
  "product_id": 123,
  "quantity": 1,
  "price": 15.99,
  "customizations": {
    "size": {"name": "Large", "price": 2.00},
    "crust": {"name": "Thin", "price": 0},
    "toppings": [
      {"name": "Pepperoni", "price": 1.50},
      {"name": "Mushrooms", "price": 1.00}
    ]
  }
}
```

## Troubleshooting

### Common Issues

1. **Cart not syncing**
   - Check AJAX URL and nonce
   - Verify WooCommerce session is active
   - Check browser console for errors

2. **Shipping methods not showing**
   - Verify shipping zones configuration
   - Check delivery method instance ID
   - Ensure order type is being passed correctly

3. **Styles not loading**
   - Verify file paths in plugin
   - Check for theme conflicts
   - Ensure proper enqueue priority

## Security

- AJAX requests are protected with nonces
- All user input is sanitized
- Cart data is validated server-side
- No direct database queries

## Performance

- React app is minified for production
- Assets are versioned for cache busting
- Lazy loading for product images
- Optimized database queries through WooCommerce

## License

GPL v2 or later - See [License](https://www.gnu.org/licenses/gpl-2.0.html)

## Support

For issues or feature requests, please open an issue on GitHub.

## Changelog

### Version 1.0.2
- Updated asset handling
- Improved delivery fee logic
- Enhanced cart synchronization

### Version 1.0.1
- Initial release
- Basic WooCommerce integration
- React menu system
