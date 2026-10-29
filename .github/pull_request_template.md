Closes #

## What changed
-

## Why
-

## Key files and endpoints
-

## How it was verified
-

## Definition of done
- [ ] Feature/unit tests for the task, and the full suite passes (`php artisan test`, `npm run test`, `npm run lint`, `next build`)
- [ ] FormRequest validation and an authorization check on every endpoint
- [ ] `AuditLogger` entry for every sensitive or financial action
- [ ] UI strings in ar/en with correct RTL
- [ ] OpenAPI (`composer api:docs`) and the Postman collection regenerated
- [ ] No N+1 queries; indexes on new tables
- [ ] Behind the right feature flag if the module is not complete
