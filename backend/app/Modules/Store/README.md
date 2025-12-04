# Store & Marketplace Module

## Purpose
E-commerce marketplace for buying/selling goods with MYXN payments.

## Expected Endpoints
- GET /api/store/products - List products
- GET /api/store/products/{id} - Product details
- POST /api/store/cart - Add to cart
- POST /api/store/checkout - Checkout
- GET /api/store/orders - Order history
- POST /api/store/sellers/register - Become a seller

## Interfaces
- StoreServiceInterface
- ProductCatalogInterface
- OrderManagementInterface
- SellerServiceInterface

## TODO
- [ ] Implement product catalog
- [ ] Add shopping cart system
- [ ] Create seller dashboard
