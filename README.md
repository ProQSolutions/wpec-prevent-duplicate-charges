WPEC Prevent Duplicate Charges
==============================
This plugin was made because we were experiencing duplicating charges with some orders through WP Ecommerce. The cause was always something different, but the root problem was that WP Ecommerce doesn't count on issues happening during the order submit process, and therefore can allow for an order to be charged more than once and produce duplicate purchase logs. This plugin doesn't fix the problems in your code, but it will prevent your customers from being charged more than once for a single order, even if caused by server issues or user error.

## What this plugin does: ##
> If for any reason (ie, PHP Fatal error or User submits form mutliple times) an order has been submitted for checkout more than once, it will not be allowed to proceed through the designated gateway, and instead redirected to transaction results. This plugin depends on the designated merchant plugin to update the purchsae order immedietly after the transaction has been run.

# When this plugin will fail: #
> When a merchant plugin's submit() function is called, if an error is thrown before the merchant plugin has had a chance to call set_transaction_details, then this plugin will not work. If this plugin does not prevent duplicate orders for you, then an error lies in the submit() function of the merchant plugin.

## How this plugin works: ##
1. When an order is submitted at checkout, we check to see if the current cart has been associated with an approved purchase log
2. If it has, then we redirect to transaction results immedietly, empty the cart, and don't let the merchant do anything
3. If it hasn't, then we add hooks to narrow in at the moment a merchant updates the transaction to "Approved."
4. At this moment, we update wpecdup_processed_cart_[uniqueid] to contain the session of the purchase_log, so we know it's been paid for