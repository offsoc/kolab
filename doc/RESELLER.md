# Reseller (Tenant) System

Let's start with defining some labels:

- `owner` - an entity owning the Kolab installation
- `user` - an entity that has a record in Kolab users database
- `admin` - a user that is system's owner staff member. Has access to Admin Cockpit, has no mailbox.
- `tenant` - an entity owning a reseller subsystem
- `reseller` - a user that is tenant's staff member. Has access to Reseller Cockpit, has no mailbox.


## System deployment

TODO


## System setup

TODO


## Creating tenant/resellers/domains

1. Create a new reseller user

```
php artisan user:create admin@reseller.kolab.io --role=reseller
```

2. Create a tenant

```
php artisan tenant:create admin@reseller.kolab.io --title="Reseller Company"
```

3. Create a public domain (for customer signups) - this should be executed
on the tenant system.

```
php artisan scalpel:domain:create --namespace=reseller.kolab.io --type=1 --status=18
```

4. List all tenants in the system

```
php artisan tenants --attr=title
```

5. Managing tenant settings

```
php artisan tenant:list-settings
php artisan scalpel:tenant-setting:create --tenant_id=<tenant-id> --key=mail.sender.address --value=noreply@reseller.kolab.io
php artisan scalpel:tenant-setting:update <setting-id> --value=noreply@reseller.kolab.io
```
For proper operation some settings need to be set for a tenant. They include:
`app.public_url`, `app.url`, `app.name`, `app.support_url`,
`mail.sender.address`, `mail.sender.name`, `mail.replyto.address`, `mail.replyto.name`.


## Plans and Packages

The `tenant:create` command clones all active plans/packages/SKUs. So, all a new tenant will need is
to define fees and cost for these new SKUs. Also maybe he does not need all of the plans/packages
or wants some new ones?

The commands below need to be executed on the tenant system.

1. Listing plans/packages

```
php artisan plan:packages
php artisan package:skus
```

2. Listing all SKUs

```
php artisan skus --attr=title --attr=cost --attr=fee
```

3. Modifying SKU

```
php artisan scalpel:sku:update <sku-id> --cost=1000 --fee=900
```


## Fees

Every SKU has a cost and fee defined. Both are monetary values (not percents).
Cost is what a customer is paying. Fee is what the system owner gets.
Tenant's profit is cost minus fee. Which means that when cost is lower than fee tenant's gonna pay
the difference.

Important facts about fees:

- Discounts lower the cost for customers, but do not impact the fee.
- Degraded users' cost is zero, but fee is getting payed by the tenant.
- SKU's `units_free` definition impacts both cost and fee in the same way.

Fees are getting applied to the tenant's wallet. Note that currently we're using
wallet of the first tenant's reseller user (see `Tenant::wallet()`). I.e. it will have to change if we
wanted to allow tenants to have more than one staff member.
