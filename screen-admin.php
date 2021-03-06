<?php
/**
 * GES code meant to be run only on frontend group administration pages.
 *
 * @since 3.7.0
 */

// create the default subscription settings during group creation and editing
function ass_default_subscription_settings_form() {
	?>
	<h4><?php _e('Email Subscription Defaults', 'bp-ass'); ?></h4>
	<p><?php _e('When new users join this group, their default email notification settings will be:', 'bp-ass'); ?></p>
	<div class="radio">
		<label><input type="radio" name="ass-default-subscription" value="no" <?php ass_default_subscription_settings( 'no' ) ?> />
			<?php _e( 'No Email (users will read this group on the web - good for any group)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="sum" <?php ass_default_subscription_settings( 'sum' ) ?> />
			<?php _e( 'Weekly Summary Email (the week\'s topics - good for large groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="dig" <?php ass_default_subscription_settings( 'dig' ) ?> />
			<?php _e( 'Daily Digest Email (all daily activity bundles in one email - good for medium-size groups)', 'bp-ass' ) ?></label>

		<?php if ( ass_get_forum_type() ) : ?>
			<label><input type="radio" name="ass-default-subscription" value="sub" <?php ass_default_subscription_settings( 'sub' ) ?> />
			<?php _e( 'New Topics Email (new topics are sent as they arrive, but not replies - good for small groups)', 'bp-ass' ) ?></label>
		<?php endif; ?>

		<label><input type="radio" name="ass-default-subscription" value="supersub" <?php ass_default_subscription_settings( 'supersub' ) ?> />
			<?php _e( 'All Email (send emails about everything - recommended only for working groups)', 'bp-ass' ) ?></label>
	</div>
	<hr />
	<?php
}
add_action ( 'bp_after_group_settings_admin' ,'ass_default_subscription_settings_form' );
add_action ( 'bp_after_group_settings_creation_step' ,'ass_default_subscription_settings_form' );

// make the change to the users' email status based on the function above
function ass_manage_members_email_update() {
	global $bp;

	if ( bp_is_groups_component() && bp_is_action_variable( 'manage-members', 0 ) ) {

		if ( !$bp->is_item_admin )
			return false;

		if ( bp_is_action_variable( 'email', 1 ) && ( bp_is_action_variable( 'no', 2 ) || bp_is_action_variable( 'sum', 2 ) || bp_is_action_variable( 'dig', 2 ) || bp_is_action_variable( 'sub', 2 ) || bp_is_action_variable( 'supersub', 2 ) ) && isset( $bp->action_variables[3] ) && is_numeric( $bp->action_variables[3] ) ) {

			$user_id = $bp->action_variables[3];
			$action = $bp->action_variables[2];

			/* Check the nonce first. */
			if ( !check_admin_referer( 'ass_member_email_status' ) )
				return false;

			ass_group_subscription( $action, $user_id, bp_get_current_group_id() );
			bp_core_add_message( __( 'User email status changed successfully', 'bp-ass' ) );
			bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) . 'admin/manage-members/' );
		}
	}
}
add_action( 'bp_actions', 'ass_manage_members_email_update' );

// Site admin can change the email settings for ALL users in a group
function ass_change_all_email_sub() {
	global $groups_template, $bp;

	if ( !is_super_admin() )
		return false;

	$group = &$groups_template->group;

	if (! $default_email_sub = ass_get_default_subscription( $group ) )
		$default_email_sub = 'no';

	echo '<p><br>'.__('Site Admin Only: update email subscription settings for ALL members to the default:', 'bp-ass').' <i>' . ass_subscribe_translate( $default_email_sub ) . '</i>.  '.__('Warning: this is not reversible so use with caution.', 'bp-ass').' <a href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'admin/manage-members/email-all/'. $default_email_sub, 'ass_change_all_email_sub' ) . '">'.__('Make it so!', 'bp-ass').'</a></p>';
}
add_action( 'bp_after_group_manage_members_admin', 'ass_change_all_email_sub' );

// change all users' email status based on the function above
function ass_manage_all_members_email_update() {
	global $bp;

	if ( bp_is_groups_component() && bp_is_action_variable( 'manage-members', 0 ) ) {

		if ( !is_super_admin() )
			return false;

		$action = bp_action_variable( 2 );

		if ( bp_is_action_variable( 'email-all', 1 ) && ( 'no' == $action || 'sum' == $action || 'dig' == $action || 'sub' == $action || 'supersub' == $action ) ) {

			if ( !check_admin_referer( 'ass_change_all_email_sub' ) )
				return false;

			$result = BP_Groups_Member::get_all_for_group( bp_get_current_group_id(), 0, 0, 0 ); // set the last value to 1 to exclude admins
			$members = $result['members'];

			foreach ( $members as $member ) {
				ass_group_subscription( $action, $member->user_id, bp_get_current_group_id() );
			}

			bp_core_add_message( __( 'All user email status\'s changed successfully', 'bp-ass' ) );
			bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) . 'admin/manage-members/' );
		}
	}
}
add_action( 'bp_actions', 'ass_manage_all_members_email_update' );

// create a form that allows admins to email everyone in the group
function ass_admin_notice_form() {
	global $bp;

	if ( groups_is_user_admin( bp_loggedin_user_id() , bp_get_current_group_id() ) || is_super_admin() ) {
		$submit_link = bp_get_groups_action_link( 'notifications' );
		?>
		<form action="<?php echo $submit_link ?>" method="post">
			<?php wp_nonce_field( 'ass_email_options' ); ?>

			<h3><?php _e('Send an email notice to everyone in the group', 'bp-ass'); ?></h3>
			<p><?php _e('You can use the form below to send an email notice to all group members.', 'bp-ass'); ?> <br>
			<b><?php _e('Everyone in the group will receive the email -- regardless of their email settings -- so use with caution', 'bp-ass'); ?></b>.</p>

			<p>
				<label for="ass-admin-notice-subject"><?php _e('Email Subject:', 'bp-ass') ?></label>
				<input type="text" name="ass_admin_notice_subject" id="ass-admin-notice-subject" value="" />
			</p>

			<p>
				<label for="ass-admin-notice-textarea"><?php _e('Email Content:', 'bp-ass') ?></label>
				<textarea value="" name="ass_admin_notice" id="ass-admin-notice-textarea"></textarea>
			</p>

			<p>
				<input type="submit" name="ass_admin_notice_send" value="<?php _e('Email this notice to everyone in the group', 'bp-ass') ?>" />
			</p>

			<br />

			<?php $welcome_email = groups_get_groupmeta( bp_get_current_group_id(), 'ass_welcome_email' ); ?>
			<?php $welcome_email_enabled = isset( $welcome_email['enabled'] ) ? $welcome_email['enabled'] : ''; ?>

			<h3><?php _e( 'Welcome Email', 'bp-ass' ); ?></h3>
			<p><?php _e( 'Send an email when a new member join the group.', 'bp-ass' ); ?></p>

			<p>
				<label>
					<input<?php checked( $welcome_email_enabled, 'yes' ); ?> type="checkbox" name="ass_welcome_email[enabled]" id="ass-welcome-email-enabled" value="yes" />
					<?php _e( 'Enable welcome email', 'bp-ass' ); ?>
				</label>
			</p>

			<p class="ass-welcome-email-field<?php if ( $welcome_email_enabled != 'yes' ) echo ' hide-if-js'; ?>">
				<label for="ass-welcome-email-subject"><?php _e( 'Email Subject:', 'bp-ass' ); ?></label>
				<input value="<?php echo isset( $welcome_email['subject'] ) ? $welcome_email['subject'] : ''; ?>" type="text" name="ass_welcome_email[subject]" id="ass-welcome-email-subject" />
			</p>

			<p class="ass-welcome-email-field<?php if ( $welcome_email_enabled != 'yes' ) echo ' hide-if-js'; ?>">
				<label for="ass-welcome-email-content"><?php _e( 'Email Content:', 'bp-ass'); ?></label>
				<textarea name="ass_welcome_email[content]" id="ass-welcome-email-content"><?php echo isset( $welcome_email['content'] ) ? $welcome_email['content'] : ''; ?></textarea>
			</p>

			<p>
				<input type="submit" name="ass_welcome_email_submit" value="<?php _e( 'Save', 'bp-ass' ); ?>" />
			</p>
		</form>
		<?php
	}
}

// This function sends an email out to all group members regardless of subscription status.
// TODO: change this function so the separate from is remove from the admin area and make it a checkbox under the 'add new topic' form. that way group admins can simply check off the box and it'll go to everyone. The benefit: notices are stored in the discussion form for later viewing. We should also alert the admin just how many people will get his post.
function ass_admin_notice() {
    if ( bp_is_groups_component() && bp_is_current_action( 'admin' ) && bp_is_action_variable( 'notifications', 0 ) ) {

	    	// Make sure the user is an admin
		if ( !groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) && ! is_super_admin() )
			return;

		if ( get_option('ass-admin-can-send-email') == 'no' )
			return;

		// make sure the correct form variables are here
		if ( ! isset( $_POST[ 'ass_admin_notice_send' ] ) )
			return;

		if ( empty( $_POST[ 'ass_admin_notice' ] ) ) {
			bp_core_add_message( __( 'The email notice was sent not sent. Please enter email content.', 'bp-ass' ), 'error' );
		} else {
			$group      = groups_get_current_group();
			$group_id   = $group->id;
			$group_name = bp_get_current_group_name();
			$group_link = bp_get_group_permalink( $group );

			if ( $group->status != 'public' ) {
				$group_link = ass_get_login_redirect_url( $group_link, 'admin_notice' );
			}

			$blogname   = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
			$subject    = $_POST[ 'ass_admin_notice_subject' ];
			$subject   .= __(' - sent from the group ', 'bp-ass') . $group_name . ' ' . $blogname;
			$subject    = apply_filters( 'ass_admin_notice_subject', $subject, $_POST[ 'ass_admin_notice_subject' ], $group_name, $blogname );
			$subject    = ass_clean_subject( $subject, false );
			$notice     = apply_filters( 'ass_admin_notice_message', $_POST['ass_admin_notice'] );
			$notice     = ass_clean_content( $notice );

			$message    = sprintf( __(
'This is a notice from the group \'%s\':

"%s"


To view this group log in and follow the link below:
%s

---------------------
', 'bp-ass' ), $group_name,  $notice, $group_link );

			$message .= __( 'Please note: admin notices are sent to everyone in the group and cannot be disabled.
If you feel this service is being misused please speak to the website administrator.', 'bp-ass' );

			$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );

			// allow others to perform an action when this type of email is sent, like adding to the activity feed
			do_action( 'ass_admin_notice', $group_id, $subject, $notice );

			// cycle through all group members
			foreach ( (array)$user_ids as $user_id ) {
				$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user

				if ( $user->user_email )
					wp_mail( $user->user_email, $subject, $message );  // Send the email

				//echo '<br>Email: ' . $user->user_email;
			}

			bp_core_add_message( __( 'The email notice was sent successfully.', 'bp-ass' ) );
			//echo '<p>Subject: ' . $subject;
			//echo '<pre>'; print_r( $message ); echo '</pre>';
		}

		bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) . 'admin/notifications/' );
	}
}
add_action( 'bp_actions', 'ass_admin_notice', 1 );

// save welcome email option
function ass_save_welcome_email() {
	if ( bp_is_groups_component() && bp_is_current_action( 'admin' ) && bp_is_action_variable( 'notifications', 0 ) ) {

		if ( ! isset( $_POST['ass_welcome_email_submit'] ) )
			return;

		if ( ! groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) && ! is_super_admin() )
			return;

		check_admin_referer( 'ass_email_options' );

		$values = stripslashes_deep( $_POST['ass_welcome_email'] );
		groups_update_groupmeta( bp_get_current_group_id(), 'ass_welcome_email', $values );

		bp_core_add_message( __( 'The welcome email option has been saved.', 'bp-ass' ) );
		bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) . 'admin/notifications/' );
	}
}
add_action( 'bp_actions', 'ass_save_welcome_email', 1 );