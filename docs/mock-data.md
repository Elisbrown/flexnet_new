# Mock data and credentials

Use `data/mock-data.sql` to seed a minimal working dataset aligned with the current schema. The script inserts an admin, roles, two locations, households, a plan, subscriptions, and successful payments.

## Admin login
- Email: `admin@flexnet.test`
- Password: `Admin@123`

## Household test accounts
- `Room 12` (Citadel Complex Holdings)
  - Phone: `679690703`
  - PIN: `1234`
- `Apt 4B` (Green Valley Residence)
  - Phone: `677123456`
  - PIN: `1234`

Run the SQL file after creating the schema:

```sql
SOURCE data/mock-data.sql;
```
