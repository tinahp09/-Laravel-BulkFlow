# V2 Multi-Profile Imports and Auto-Mapping Design

## Goal

Make the demo prove that BulkFlow imports more than one resource type, while
reducing manual column mapping for common vendor CSV/XLSX headings.

## Scope

- Register three developer-owned demo profiles: `users`, `products`, and
  `orders`.
- Add demo database tables/models for Products and Orders.
- Add a deterministic auto-mapping proposal to the Vue package.
- Preserve manual mappings and profile defaults as explicit, editable choices.

## Profiles

| Key | Attributes | Upsert key | Required fields |
| --- | --- | --- | --- |
| users | name, email, password | email | name, email, password |
| products | sku, name, price, stock | sku | sku, name, price, stock |
| orders | reference, customer_email, total, status | reference | reference, customer_email, total, status |

The Laravel profile remains the only authority for model class, allowed
attributes, validation and upsert behavior. Browser input never chooses these.

## Auto-Mapping

The Vue package exports a pure `proposeMapping(headers, profile)` helper.

1. Start with a profile's `defaultMapping`, but keep only headings found in the
   uploaded source.
2. For unmapped headers, normalize case, trim surrounding whitespace, and
   replace spaces, hyphens and underscores with a canonical separator.
3. Match normalized headings to an allowed destination attribute or a fixed
   built-in alias. Initial aliases include `email_address` and `ایمیل` for
   `email`, `product_sku` for `sku`, `order_number` for `reference`, and
   `amount` for `total`.
4. Do not create duplicate destination mappings. Do not map anything outside
   the profile's attribute list.

Mapping priority is: explicit template selection, then manually edited values,
then profile defaults plus auto-mapping proposal. The proposal is only a
starting value in the wizard and is never persisted automatically.

## UI flow

The demo loads all registered profiles. After an upload, it calls
`proposeMapping` using the selected profile and returned headers, then passes
the proposed mapping to the existing editable wizard. Changing profile resets
the current upload, template selection and proposal.

## Verification

- Laravel feature tests list and dispatch each of the three profiles.
- Unit tests prove alias matching, Persian email matching, header normalization,
  default-mapping precedence, and rejection of duplicate/unknown destinations.
- Wizard tests show proposed mappings after a preview and preserve manual
  selection.
- A Playwright smoke test uploads a Products CSV with vendor headings, confirms
  the proposed mapping, queues it, and observes completion.
