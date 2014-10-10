<?php
/**
 * Plugin Name: WPEC Prevent Duplicate Charges
 * Description: Prevents orders from being approved through the merchant more than once, preventing duplicate charges.
 * Version: 1.0
 * Author: Pro Q Solutions
 * Author URI: http://www.proqsolutions.com
 */

/**
 * What this plugin does:
 * 		If for any reason (ie, PHP Fatal error or User submits form mutliple times) an order has been submitted for
 * 		checkout more than once, it will not be allowed to proceed through the designated gateway, and instead
 * 		redirected to transaction results. This plugin depends on the designated merchant plugin to update
 * 		the purchsae order immedietly after the transaction has been run.
 * 
 * When this plugin will fail:
 * 		When a merchant plugin's submit() function is called, if an error is thrown before the merchant plugin has had a
 * 		chance to call set_transaction_details, then this plugin will not work. If this plugin does not prevent duplicate
 * 		orders for you, then an error lies in the submit() function of the merchant plugin.
 * 
 */

/**
 * How this plugin works:
 * 1. When an order is submitted at checkout, we check to see if the current cart has been associated with an approved purchase log
 * 2. If it has, then we redirect to transaction results immedietly, empty the cart, and don't let the merchant do anything
 * 3. If it hasn't, then we add hooks to narrow in at the moment a merchant updates the transaction to "Approved."
 * 4. At this moment, we update wpecdup_processed_cart_[uniqueid] to contain the session of the purchase_log, so we know it's been paid for
 */



//This action is triggered first when an order is submitted. Found in wpsc_submit_checkout() in ajax.php
add_action('wpsc_before_submit_checkout','wpecdup_before_submit_checkout');

//This function will be triggered when a user submits their order. Not all wpec setups use this, so we
//won't rely on this function to prevent duplicate orders, only to redirect to transaction results
//if the order was already paid for.
function wpecdup_before_submit_checkout() {
	global $wpsc_cart;

	//Let's find out if this cart has been processed
	$processed_session_id = wpecdup_has_been_paid($wpsc_cart,get_current_user_id());

	if($processed_session_id ) {
		//This cart has been processed already!
		//TODO: Empty cart here
		//Proceed to transaction results

		//Get redirect url
		$transaction_url_with_sessionid = add_query_arg( 'sessionid', $processed_session_id, get_option( 'transact_url' ) );
		wp_redirect( $transaction_url_with_sessionid );
		exit();//Let's get out of here so we don't process anything else
	}

	//Since we are now checking out, let's add a hook for when the gateway is triggered
	add_action('wpsc_submit_checkout_gateway','wpecdup_submit_checkout_gateway',1,2);

}

//Once we notice a purchase log being submitted to the merchant gateway, we start out process
function wpecdup_submit_checkout_gateway($submitted_gateway, $purchase_log) {
	//The user may have not submitted the checkout, in which case we need to double
	//check that the cart has not been paid for.
	$userID = $purchase_log->get('user_id');
	if(wpecdup_has_been_paid(wpsc_get_customer_cart($userID),$userID))
		exit(); //might want to pass up an error instead, but this will do for now. We will only reach this point if an admin is submitting the order twice, or someone has a custom wpec

	//Gateway is abou to begin, we need to make sure when the gateway marks the purchase order as approved, that we catch it right away
	add_action('wpsc_purchase_log_update','wpecdup_purchase_log_update');
}

//This will only trigger, in theory, if the gateway is updating the purchase log status
function wpecdup_purchase_log_update($purchase_log) {
	global $wpsc_cart;

	//Only proceed if the merchant gateway is setting purchase log to "approved"
	if($purchase_log->get( 'processed' ) != 3)
		return;

	//one last check, we only want to mark the cart as paid for if the user is logged in and submitting these scripts
	if($purchase_log->get('user_id') != get_current_user_id())
		return;

	//Now let's log this immedietly so we know not to charge for this order again
	update_user_meta(get_current_user_id(), 'wpecdup_processed_cart_'.$wpsc_cart->unique_id,$purchase_log->get( 'sessionid' ));
}

//This function returns the session id tied to the cart if the cart has been processed
function wpecdup_has_been_paid($cart,$userid) {
	if($cart->unique_id)
		return $processed_session_id = get_user_meta($userid, 'wpecdup_processed_cart_'.$cart->unique_id);
	
	return false;
}


/**
 *  Below is code for testing the plugin. The following will throw an error when a transaction is approved. This is the most common source of possible duplicate orders.
 *  Uncomment it to run a test
 */

/*
add_action( 'wpsc_update_purchase_log_status', 'wpecdup_throw_error', 10, 4 );
function wpecdup_throw_error($id, $status, $old_status, $purchase_log) {
	if($status == 3) {
		throw new Exception('A bogus error has occured!');
	}
}
*/