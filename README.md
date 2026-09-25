# SmartCustomer Reviews for Magento 2

`SmartCustomer_Reviews` connects Magento orders and optional storefront widgets to
SmartCustomer. The Magento module's Composer package name is
`sitejabber/module-reviews` (kept for compatibility with existing installs).

This repository's root **is** the Magento module root: it contains
`registration.php`, `composer.json`, `etc/`, `Model/`, and `view/`. The checkout
directory name does not determine Magento's module identity. For a filesystem
installation, place these files directly in
`<magento-root>/app/code/SmartCustomer/Reviews/`—do not add an extra
`module-reviews` directory at that destination.

## Requirements

- An installed Magento Open Source or Adobe Commerce 2.4 store. The module has
  been exercised in this project's Magento Open Source **2.4.9** development
  store; other versions have not been verified here. Use the PHP and service
  versions supported by your Magento release.
- PHP `curl` and `openssl` extensions; the Magento CLI and a working Magento
  `default` cron group. Outbound HTTPS access to `api.smartcustomer.com` is
  needed for order delivery and account connection. Storefront widgets load
  JavaScript from `www.smartcustomer.com`.
- A SmartCustomer business account with a Client ID and Client Encryption Key
  from [the account page](https://biz.smartcustomer.com/account).

## Install from the filesystem

Run these commands as the Magento filesystem owner, from the Magento root:

```bash
git clone https://github.com/smartcustomer-reviews/magento-extension.git app/code/SmartCustomer/Reviews
bin/magento module:enable SmartCustomer_Reviews
bin/magento setup:upgrade
bin/magento cache:flush
```

On production deployments, also run your normal compilation and static content
deployment steps (for example, `bin/magento setup:di:compile` and
`bin/magento setup:static-content:deploy -f`). Deploy the same module code to
every Magento application node. To update a filesystem installation, update
the code and run `bin/magento setup:upgrade` and the deployment steps again.

After a release tag is available, Composer installation from this repository
is also possible:

```bash
composer config repositories.smartcustomer-reviews vcs https://github.com/smartcustomer-reviews/magento-extension.git
composer require sitejabber/module-reviews:^0.5
bin/magento module:enable SmartCustomer_Reviews
bin/magento setup:upgrade
bin/magento cache:flush
```

The Composer package name is intentionally unchanged. Git tags determine the
Composer version; the first tag for this source should be `v0.5.0` or `0.5.0`.

## Configure

1. In Magento Admin, open **Stores → Configuration → SmartCustomer →
   Configuration** for each store view you want to connect. Enter the Client ID
   and Client Encryption Key, set **Enabled** to **Yes**, and save.
2. Use **SmartCustomer → [store] → Connect to your SmartCustomer account** in
   the admin menu to finish the account connection. The connection flow
   redirects through `https://api.smartcustomer.com/v2/magento/access`.
3. Confirm Magento cron is running. If the store has no Magento crontab yet,
   run `bin/magento cron:install` as its filesystem owner. The module's delivery
   job is scheduled every minute in the `default` group. Magento cron may need
   two runs before a newly scheduled job executes.

Optional **SmartCustomer → Widgets** settings enable Instant Feedback on the
order success page and product rating/review widgets on product pages. Add the
product widgets through Magento's widget/layout placement tools. Enabling the
integration alone does not automatically place product widgets.

## How order delivery works

```text
Magento saves an order
  → sales_order_save_commit_after records its ID in smartcustomer_reviews_outbox
  → Magento cron reads the order and sends it to the SmartCustomer API
  → success is marked sent; failures are retried with increasing delay
```

The order save observer makes a local database write only. It catches outbox
write errors so an integration failure does not throw an exception from the
observer. The checkout request does not wait for an HTTP request to
SmartCustomer. Cron posts to `https://api.smartcustomer.com/v2/magento/orders`
with the order ID, status, currency, customer name and email, and order totals.
It sends the configured Client ID and an encrypted form of the Client
Encryption Key as API query parameters. Treat HTTP request logs and the
configured key as sensitive.

If the API is unavailable, the outbox retries with exponential delay (up to
one hour between attempts), then discards a delivery after 48 attempts. A
later save of the same order can enqueue it again. Delivery should therefore
be treated as **at least once**, not exactly once. Sent rows are retained for
seven days and discarded rows for 30 days. A working Magento cron is required
for eventual delivery; checkout still works while the API is unavailable.

The admin connection flow can also request a historical sync of completed
orders since a selected date. That sync is triggered from the admin flow and
uses `https://api.smartcustomer.com/v2/magento/orders/sync`; unlike the outbox
job, it runs during the admin request. It does not run during customer checkout.

The order success page has a separate Magento block. It exposes conversion
data to storefront JavaScript and, when Instant Feedback is enabled, loads the
SmartCustomer widget script. Product rating and review widgets also load that
script when placed and enabled. These storefront features are separate from
the cron order delivery path.

## Check delivery

`bin/magento module:status SmartCustomer_Reviews` checks whether the module is
enabled. Check `var/log/cron.log` and the `smartcustomer_reviews_outbox` table
when investigating delayed deliveries. For example:

```sql
SELECT order_id, status, attempts, available_at, last_error
FROM smartcustomer_reviews_outbox
ORDER BY entity_id DESC
LIMIT 20;
```

The test store in this project's parent repository bind mounts this module into
`app/code/SmartCustomer/Reviews` and provides `magento/bin/test/unit` for the
included unit tests.

## License

Open Software License 3.0. See [LICENSE](LICENSE).
