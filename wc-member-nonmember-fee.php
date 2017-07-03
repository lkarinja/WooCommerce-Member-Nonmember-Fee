<?php
/*
	Plugin Name: WooCommerce Member Nonmember Fee
	Description: Allows you to add a member and nonmember fee to WooCommerce
	Version: 1.0.0
	Author: <a href="http://shop.terrytsang.com">Terry Tsang</a>, <a href="https://github.com/lkarinja">Leejae Karinja</a>
	License: GPL2
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*
	Copyright 2012-2016 Terry Tsang (email: terrytsang811@gmail.com)
	Copyright 2017 Leejae Karinja

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Prevents execution outside of core WordPress
if(!defined('ABSPATH')){
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

// Define plugin name
define('wc_plugin_name_member_nonmember_fee', 'WooCommerce Member and Nonmember Fees');

// Define plugin version
define('wc_version_member_nonmember_fee', '1.0.0');

// If WooCommerce plugin is installed and active
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	// If plugin class was not created yet
	if(!class_exists('WooCommerce_Member_Nonmember_Fee')){
		class WooCommerce_Member_Nonmember_Fee
		{
			// Plugin information
			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			// Plugin options
			var $textdomain;
		    var $types;
		    var $fee_options;
		    var $saved_fee_options;

			/**
			 * Initialize plugin and plugin data
			 */
			public function __construct()
			{
				// Sets plugin information
				WooCommerce_Member_Nonmember_Fee::$plugin_prefix = 'wc_member_nonmember_fee_';
				WooCommerce_Member_Nonmember_Fee::$plugin_basefile = plugin_basename(__FILE__);
				WooCommerce_Member_Nonmember_Fee::$plugin_url = plugin_dir_url(WooCommerce_Member_Nonmember_Fee::$plugin_basefile);
				WooCommerce_Member_Nonmember_Fee::$plugin_path = trailingslashit(dirname(__FILE__));

				$this->textdomain = 'wc-member-nonmember-fee';

				// Types of fees that can be used
				$this->types = array('fixed' => 'Fixed Fee', 'percentage' => 'Cart Percentage(%)');

				// Options for member and nonmember fees
				$this->fee_options = array(
					'nonmember_extra_fee_option_label' => 'Nonmember Fee',
					'nonmember_extra_fee_option_type' => 'fixed',
					'nonmember_extra_fee_option_cost' => 0,
					'nonmember_extra_fee_option_taxable' => false,
					'member_extra_fee_option_label' => 'Member Fee',
					'member_extra_fee_option_type' => 'fixed',
					'member_extra_fee_option_cost' => 0,
					'member_extra_fee_option_taxable' => false,
					'member_extra_fee_option_group_id' => null,
				);
				$this->saved_fee_options = array();

				add_action('woocommerce_init', array(&$this, 'init'));
			}

			/**
			 * Initializes this plugin with the actions it will process
			 */
			public function init()
			{
				add_action('admin_menu', array(&$this, 'add_menu_extra_fee_option'));
				add_action('woocommerce_cart_calculate_fees', array(&$this, 'woo_add_extra_fee'));
			}

			/**
			 * Register Tsang's stylesheet
			 */
			function tsang_plugin_admin_init()
			{
				wp_register_style('tsangPluginStylesheet', plugins_url('css/admin.css', __FILE__));
			}

			/**
			 * Uses Tsang's stylesheet on the admin page
			 */
			function tsang_plugin_admin_styles()
			{
				wp_enqueue_style('tsangPluginStylesheet');
			}

			/**
			 * Adds specified fees for users in member group and nonmember group
			 */
			public function woo_add_extra_fee()
			{
				// Global for getting cart information
				global $woocommerce;
				// Global for querying the database
				global $wpdb;

				// Options for nonmembers
				$nonmember_extra_fee_option_label = get_option('nonmember_extra_fee_option_label') ? get_option('nonmember_extra_fee_option_label') : 'Nonmember Fee';
				$nonmember_extra_fee_option_cost = get_option('nonmember_extra_fee_option_cost') ? get_option('nonmember_extra_fee_option_cost') : 0;
				$nonmember_extra_fee_option_type = get_option('nonmember_extra_fee_option_type') ? get_option('nonmember_extra_fee_option_type') : 'fixed';
				$nonmember_extra_fee_option_taxable = get_option('nonmember_extra_fee_option_taxable') ? get_option('nonmember_extra_fee_option_taxable') : false;

				// Options for members
				$member_extra_fee_option_label = get_option('member_extra_fee_option_label') ? get_option('member_extra_fee_option_label') : 'Member Fee';
				$member_extra_fee_option_cost = get_option('member_extra_fee_option_cost') ? get_option('member_extra_fee_option_cost') : 0;
				$member_extra_fee_option_type = get_option('member_extra_fee_option_type') ? get_option('member_extra_fee_option_type') : 'fixed';
				$member_extra_fee_option_taxable = get_option('member_extra_fee_option_taxable') ? get_option('member_extra_fee_option_taxable') : false;
				$member_extra_fee_option_group_id = get_option('member_extra_fee_option_group_id') ? get_option('member_extra_fee_option_group_id') : null;

				// Get items in user's cart
				$items = $woocommerce->cart->get_cart();

				$total = 0.0;
				// For each item in the cart
				foreach($items as $item => $values){
					// If the item is not excluded from the fees, use those items in calculation of the total
					if(!array_shift(wc_get_product_terms($values['product_id'], 'pa_exclude_from_fee', array('fields' => 'names')))){
						$total += (get_post_meta($values['product_id'] , '_price', true) * $values['quantity']);
					}
				}

				// If the nonmember fee is a percentage
				if($nonmember_extra_fee_option_type == 'percentage'){
					// Calculate the nonmember fee based on percent of total cart
					$nonmember_extra_fee_option_cost = ($nonmember_extra_fee_option_cost / 100) * $total;
				}
				// Round nonmember fee to 2 decimal places
				$nonmember_extra_fee_option_cost = round($nonmember_extra_fee_option_cost, 2);

				// If the member fee is a percentage
				if($member_extra_fee_option_type == 'percentage'){
					// Calculate the member fee based on percent of total cart
					$member_extra_fee_option_cost = ($member_extra_fee_option_cost / 100) * $total;
				}
				// Round member fee to 2 decimal places
				$member_extra_fee_option_cost = round($member_extra_fee_option_cost, 2);

				// Get user's ID
				$customer_id = $woocommerce->customer->get_id();
				// If the user is not a guest user
				if(is_numeric($customer_id)){
					$user_id = intval($customer_id);
					if($user_id > 0){
						// Query the database and get the groups of the user
						$user_group_table = _groups_get_tablename('user_group');
						$user_group = $wpdb->get_col($wpdb->prepare(
							"SELECT group_id FROM $user_group_table WHERE user_id = %d",
							$user_id
						));

						// If the user is in the member group
						if(in_array($member_extra_fee_option_group_id, $user_group)){
							// Add the member fee
							$woocommerce->cart->add_fee(__($member_extra_fee_option_label, 'woocommerce'), $member_extra_fee_option_cost, $member_extra_fee_option_taxable);
						// If the user is not in the member group
						}else{
							// Add the nonmember fee
							$woocommerce->cart->add_fee(__($nonmember_extra_fee_option_label, 'woocommerce'), $nonmember_extra_fee_option_cost, $nonmember_extra_fee_option_taxable);
						}
					}
				// If the user is a guest
				}else{
					// Add the nonmember fee
					$woocommerce->cart->add_fee(__($nonmember_extra_fee_option_label, 'woocommerce'), $nonmember_extra_fee_option_cost, $nonmember_extra_fee_option_taxable);
				}
			}

			/**
			 * Add page to WooCommerce menu for fee options
			 */
			function add_menu_extra_fee_option()
			{
				$wc_page = 'woocommerce';
				$comparable_settings_page = add_submenu_page(
					$wc_page,
					__('Member Nonmember Fee', $this->textdomain),
					__('Member Nonmember Fee', $this->textdomain),
					'manage_options',
					'wc-member-nonmember-fee',
					array(
						&$this,
						'settings_page_extra_fee_option'
					)
				);
				add_action('admin_print_styles-' . $comparable_settings_page, array(&$this, 'tsang_plugin_admin_styles'));
			}

			/**
			 * Creates options page for this plugin
			 */
			public function settings_page_extra_fee_option()
			{
				// If options should be saved
				if(isset($_POST['submitted']))
				{
					check_admin_referer( $this->textdomain );

					// Saved options for nonmember fee
					$this->saved_fee_options['nonmember_extra_fee_option_label'] = !isset($_POST['nonmember_extra_fee_option_label']) ? 'Nonmember Fee' : $_POST['nonmember_extra_fee_option_label'];
					$this->saved_fee_options['nonmember_extra_fee_option_cost'] = !isset($_POST['nonmember_extra_fee_option_cost']) ? 0 : $_POST['nonmember_extra_fee_option_cost'];
					$this->saved_fee_options['nonmember_extra_fee_option_type'] = !isset($_POST['nonmember_extra_fee_option_type']) ? 'fixed' : $_POST['nonmember_extra_fee_option_type'];
					$this->saved_fee_options['nonmember_extra_fee_option_taxable'] = !isset($_POST['nonmember_extra_fee_option_taxable']) ? false : $_POST['nonmember_extra_fee_option_taxable'];

					// Saved options for member fee
					$this->saved_fee_options['member_extra_fee_option_label'] = !isset($_POST['member_extra_fee_option_label']) ? 'Member Fee' : $_POST['member_extra_fee_option_label'];
					$this->saved_fee_options['member_extra_fee_option_cost'] = !isset($_POST['member_extra_fee_option_cost']) ? 0 : $_POST['member_extra_fee_option_cost'];
					$this->saved_fee_options['member_extra_fee_option_type'] = !isset($_POST['member_extra_fee_option_type']) ? 'fixed' : $_POST['member_extra_fee_option_type'];
					$this->saved_fee_options['member_extra_fee_option_taxable'] = !isset($_POST['member_extra_fee_option_taxable']) ? false : $_POST['member_extra_fee_option_taxable'];
					$this->saved_fee_options['member_extra_fee_option_group_id'] = !isset($_POST['member_extra_fee_option_group_id']) ? null : $_POST['member_extra_fee_option_group_id'];

					// For all options
					foreach($this->fee_options as $field => $value)
					{
						$option_extra_fee_option = get_option($field);

						// If there was an update to an option
						if($option_extra_fee_option != $this->saved_fee_options[$field]){
							// Save the new value of that option
							update_option($field, $this->saved_fee_options[$field]);
						}
					}

					// Display a save message
					echo '<div id="message" class="updated fade"><p>' . __( 'WooCommerce Member Nonmember Fee options saved.', $this->textdomain ) . '</p></div>';
				}

				// Options for nonmembers
				$nonmember_extra_fee_option_label = get_option('nonmember_extra_fee_option_label') ? get_option('nonmember_extra_fee_option_label') : 'Nonmember Fee';
				$nonmember_extra_fee_option_cost = get_option('nonmember_extra_fee_option_cost') ? get_option('nonmember_extra_fee_option_cost') : 0;
				$nonmember_extra_fee_option_type = get_option('nonmember_extra_fee_option_type') ? get_option('nonmember_extra_fee_option_type') : 'fixed';
				$nonmember_extra_fee_option_taxable = get_option('nonmember_extra_fee_option_taxable') ? get_option('nonmember_extra_fee_option_taxable') : false;

				// Options for members
				$member_extra_fee_option_label = get_option('member_extra_fee_option_label') ? get_option('member_extra_fee_option_label') : 'Member Fee';
				$member_extra_fee_option_cost = get_option('member_extra_fee_option_cost') ? get_option('member_extra_fee_option_cost') : 0;
				$member_extra_fee_option_type = get_option('member_extra_fee_option_type') ? get_option('member_extra_fee_option_type') : 'fixed';
				$member_extra_fee_option_taxable = get_option('member_extra_fee_option_taxable') ? get_option('member_extra_fee_option_taxable') : false;
				$member_extra_fee_option_group_id = get_option('member_extra_fee_option_group_id') ? get_option('member_extra_fee_option_group_id') : null;

				// Tax options
				$nonmember_checked_taxable = '';
				$member_checked_taxable = '';

				if($nonmember_extra_fee_option_taxable)
					$nonmember_checked_taxable = 'checked="checked"';

				if($member_extra_fee_option_taxable)
					$member_checked_taxable = 'checked="checked"';

				$actionurl = $_SERVER['REQUEST_URI'];
				$nonce = wp_create_nonce($this->textdomain);

				// HTML/inline PHP for the options page in the WooCommerce menu
				?>
				<div id="icon-options-general" class="icon32"></div>
				<h3><?php _e( 'Member Nonmember Fee', $this->textdomain); ?></h3>
				<table width="90%" cellspacing="2">
				  <tr>
					<td width="70%" valign="top">
					  <form action="<?php echo $actionurl; ?>" method="post">
						<table>
						  <tbody>

							<tr>
							  <td colspan="2">
								<table class="widefat auto" cellspacing="2" cellpadding="2" border="0">
								  <tr>
									<td><?php _e('Label', $this->textdomain); ?></td>
									<td>
									  <input type="text" id="nonmember_extra_fee_option_label" name="nonmember_extra_fee_option_label" value="<?php echo $nonmember_extra_fee_option_label; ?>" size="30" />
									</td>
								  </tr>
								  <tr>
									<td><?php _e('Amount', $this->textdomain); ?></td>
									<td>
									  <input type="text" id="nonmember_extra_fee_option_cost" name="nonmember_extra_fee_option_cost" value="<?php echo $nonmember_extra_fee_option_cost; ?>" size="10" />
									</td>
								  </tr>
								  <tr>
									<td width="25%"><?php _e('Type', $this->textdomain); ?></td>
									<td>
									  <select name="nonmember_extra_fee_option_type">
										<option value="fixed" <?php if($nonmember_extra_fee_option_type == 'fixed') { echo 'selected="selected"'; } ?>><?php _e('Fixed Fee', $this->textdomain); ?></option>
										<option value="percentage" <?php if($nonmember_extra_fee_option_type == 'percentage') { echo 'selected="selected"'; } ?>><?php _e('Cart Percentage(%)', $this->textdomain); ?></option>
									  </select>
									</td>
								  </tr>
								  <tr>
									<td width="25%"><?php _e('Taxable', $this->textdomain); ?></td>
									<td>
									  <input class="checkbox" name="nonmember_extra_fee_option_taxable" id="nonmember_extra_fee_option_taxable" value="0" type="hidden">
									  <input class="checkbox" name="nonmember_extra_fee_option_taxable" id="nonmember_extra_fee_option_taxable" value="1" <?php echo $nonmember_checked_taxable; ?> type="checkbox">
									</td>
								  </tr>
								</table>
							  </td>
							</tr>

							<tr>
							  <td colspan="2">
								<table class="widefat auto" cellspacing="2" cellpadding="2" border="0">
								  <tr>
									<td><?php _e('Label', $this->textdomain); ?></td>
									<td>
									  <input type="text" id="member_extra_fee_option_label" name="member_extra_fee_option_label" value="<?php echo $member_extra_fee_option_label; ?>" size="30" />
									</td>
								  </tr>
								  <tr>
									<td><?php _e('Amount', $this->textdomain); ?></td>
									<td>
									  <input type="text" id="member_extra_fee_option_cost" name="member_extra_fee_option_cost" value="<?php echo $member_extra_fee_option_cost; ?>" size="10" />
									</td>
								  </tr>
								  <tr>
									<td width="25%"><?php _e('Type', $this->textdomain); ?></td>
									<td>
									  <select name="member_extra_fee_option_type">
										<option value="fixed" <?php if($member_extra_fee_option_type == 'fixed') { echo 'selected="selected"'; } ?>><?php _e('Fixed Fee', $this->textdomain); ?></option>
										<option value="percentage" <?php if($member_extra_fee_option_type == 'percentage') { echo 'selected="selected"'; } ?>><?php _e('Cart Percentage(%)', $this->textdomain); ?></option>
									  </select>
									</td>
								  </tr>
								  <tr>
									<td width="25%"><?php _e('Taxable', $this->textdomain); ?></td>
									<td>
									  <input class="checkbox" name="member_extra_fee_option_taxable" id="member_extra_fee_option_taxable" value="0" type="hidden">
									  <input class="checkbox" name="member_extra_fee_option_taxable" id="member_extra_fee_option_taxable" value="1" <?php echo $member_checked_taxable; ?> type="checkbox">
									</td>
								  </tr>
								  <tr>
									<td><?php _e('Group ID', $this->textdomain); ?></td>
									<td>
									  <input type="text" id="member_extra_fee_option_group_id" name="member_extra_fee_option_group_id" value="<?php echo $member_extra_fee_option_group_id; ?>" size="10" />
									</td>
								  </tr>
								</table>
							  </td>
							</tr>

							<tr>
							  <td colspan=2">
								<input class="button-primary" type="submit" name="Save" value="<?php _e('Save Options', $this->textdomain); ?>" id="submitbutton" />
								<input type="hidden" name="submitted" value="1" /> 
								<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
							  </td>
							</tr>

						  </tbody>
						</table>
					  </form>
					</td>
				  </tr>
				</table>
				<br />
				<?php
			}
		}
	}
	$WooCommerce_Member_Nonmember_Fee = new WooCommerce_Member_Nonmember_Fee();
// If WooCommerce plugin is not installed or active
}else{
	add_action('admin_notices', 'wc_member_nonmember_fee_error_notice');
	function wc_nonmember_extra_fee_option_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__(wc_plugin_name_extra_fee_option.' requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.').'</p></div>';
		}
	}
}
?>