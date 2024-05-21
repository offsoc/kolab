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

TODO: How to create tenant, resellers, public domains?


## Plans and Packages

The `tenant:create` command clones all active plans/packages/SKUs. So, all a new tenant will need is
to define fees and cost for these new SKUs. Also maybe he does not need all of the plans/packages
or wants some new ones?

TODO: How? With deployment seeder or CLI commands, admin UI?


## Fees

TODO: How to set a fee? With deployment seeder or CLI commands, admin UI?

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

TODO: Examples?
