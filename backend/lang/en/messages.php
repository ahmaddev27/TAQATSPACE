<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Messages
    |--------------------------------------------------------------------------
    |
    | User-facing messages emitted by controllers and services through the
    | ApiResponse envelope, aborts and domain exceptions. Keys are grouped by
    | domain to keep them discoverable.
    |
    */

    // Generic / shared
    'unauthenticated' => 'Unauthenticated.',
    'insufficient_permissions' => 'Insufficient permissions.',
    'unauthorized_action' => 'This action is unauthorized.',
    'no_workspace' => 'You have no workspace.',
    'no_workspace_to_update' => 'You have no workspace to update.',
    'no_workspace_to_publish' => 'You have no workspace to publish to.',
    'no_workspace_found_account' => 'No workspace found for this account.',
    'no_workspace_found_owner' => 'No workspace found for this owner.',

    // Auth
    'account_suspended' => 'Account suspended.',
    'account_pending' => 'Account pending verification.',
    'invalid_credentials' => 'Invalid email or password.',
    'current_password_incorrect' => 'The current password is incorrect.',
    'email_already_verified' => 'Email already verified.',
    'verification_link_sent' => 'Verification link sent.',
    'invalid_verification_link' => 'Invalid verification link.',

    // Password reset
    'reset_link_sent' => 'If that email exists, a reset link has been sent.',
    'password_reset_success' => 'Password has been reset successfully.',
    'invalid_reset_token' => 'Invalid or expired reset token.',

    // SSO
    'sso_missing_code' => 'Missing exchange code.',
    'sso_invalid_code' => 'Invalid or expired exchange code.',
    'sso_user_not_found' => 'User not found.',

    // Onboarding (SSO role selection)
    'onboarding_completed' => 'Your account is ready.',
    'onboarding_already_completed' => 'Your account has already been set up.',

    // Profile
    'profile_updated' => 'Profile updated successfully.',
    'password_changed' => 'Password changed successfully.',

    // Workspace
    'workspace_not_found' => 'Workspace not found.',
    'workspace_created' => 'Workspace created and submitted for review.',
    'workspace_updated' => 'Workspace updated.',
    'workspace_status_updated' => 'Workspace status updated.',
    'workspace_already_registered' => 'You already have a registered workspace.',

    // Photos
    'photos_uploaded' => 'Photos uploaded.',
    'photo_removed' => 'Photo removed.',
    'photos_max_reached' => 'A workspace may have at most :max photos.',
    'photo_not_found' => 'Photo not found on this workspace.',

    // Seats
    'seat_deleted' => 'Seat deleted.',
    'seat_type_pricing_updated' => 'Seat type pricing updated.',
    'seat_capacity_reached' => 'Seat capacity reached for this workspace.',
    'seat_number_exists' => 'A seat with this number already exists in the workspace.',
    'seat_member_no_subscription' => 'Member does not have an active subscription to this workspace.',
    'seat_member_already_holds' => 'Member already holds a seat in this workspace.',
    'seat_only_available_deletable' => 'Only available seats can be deleted.',
    'seat_referenced_by_subscription' => 'Seat is referenced by a subscription and cannot be deleted.',
    'seat_not_in_workspace' => 'The selected seat does not belong to this workspace.',
    'seat_unavailable' => 'The selected seat is no longer available. Please choose another seat.',

    // Members
    'member_not_found' => 'Member not found.',
    'member_not_in_workspace' => 'Member is not part of this workspace.',
    'member_status_updated' => 'Member status updated.',

    // Bookings / subscriptions
    'booking_submitted' => 'Booking request submitted.',
    'booking_approved' => 'Booking approved.',
    'booking_rejected' => 'Booking rejected.',
    'subscription_cancelled' => 'Subscription cancelled.',
    'booking_not_accepting' => 'This workspace is not accepting booking requests.',
    'booking_already_pending' => 'You already have a pending booking request.',
    'booking_already_subscribed' => 'You already have an active subscription to this workspace.',
    'booking_already_reviewed' => 'This booking request has already been reviewed.',
    'subscription_already_cancelled' => 'This subscription is already cancelled.',
    'subscription_no_access' => 'You do not have access to this subscription.',

    // Packages
    'package_created' => 'Package created successfully.',
    'package_updated' => 'Package updated successfully.',
    'package_deleted' => 'Package deleted successfully.',
    'package_assigned' => 'Package assigned to member.',
    'package_unassigned' => 'Package unassigned from member.',
    'package_not_found' => 'Package not found.',

    // Invoices
    'invoice_not_found' => 'Invoice not found.',
    'invoice_created' => 'Invoice created.',
    'invoice_marked_paid' => 'Invoice marked as paid.',
    'invoice_reverted_pending' => 'Invoice reverted to pending.',
    'receipt_uploaded' => 'Receipt uploaded and invoice marked paid.',
    'reminder_sent' => 'Reminder sent.',
    'invoice_member_no_subscription' => 'This member has no active subscription in your workspace.',
    'invoice_already_paid' => 'This invoice has already been paid.',
    'invoice_reminder_recently_sent' => 'A reminder was already sent for this invoice in the last 24 hours.',

    // Announcements
    'announcement_created' => 'Announcement created.',
    'announcement_updated' => 'Announcement updated.',
    'announcement_not_found' => 'Announcement not found.',
    'announcement_deleted' => 'Announcement deleted.',

    // Messages
    'message_sent' => 'Message sent.',
    'broadcast_sent' => 'Broadcast sent.',
    'messages_marked_read' => 'Messages marked as read.',
    'recipient_not_active_member' => 'Recipient is not an active member of your workspace.',
    'no_active_membership' => 'No active membership found.',

    // Notifications
    'all_notifications_marked_read' => 'All notifications marked as read.',
    'notifications_marked_read' => 'Notifications marked as read.',
    'notification_deleted' => 'Notification deleted.',
    'notification_not_found' => 'Notification not found.',

    // Reviews
    'review_submitted' => 'Review submitted successfully.',
    'review_only_subscribed' => 'You can only review a workspace you are or were subscribed to.',
    'review_already_reviewed' => 'You have already reviewed this workspace.',

    // Admin content / landing / settings
    'content_updated' => 'Content updated.',
    'landing_content_updated' => 'Landing content updated.',
    'landing_image_uploaded' => 'Image uploaded.',
    'user_status_updated' => 'User status updated.',
    'messaging_settings_updated' => 'Messaging settings updated.',
    'workspace_messaging_updated' => 'Workspace messaging settings updated.',

    // Messaging test
    'test_email_sent' => 'Test email sent to :to.',
    'test_email_failed' => 'Test email failed: :error',
    'test_sms_sent' => 'Test SMS sent to :to.',
    'test_sms_failed' => 'Test SMS failed: :error',
    'sms_not_configured' => 'SMS is not configured for the platform.',

    // Broadcast (compose & send to an audience)
    'broadcast_queued' => 'Your message has been queued for delivery.',
    'broadcast_recipients_required' => 'Select at least one recipient.',
    'broadcast_email_not_configured_platform' => 'Email is not configured for the platform. Configure SMTP before sending.',
    'broadcast_sms_not_configured_platform' => 'SMS is not configured for the platform. Configure an SMS provider before sending.',
    'broadcast_email_not_configured_workspace' => 'Email is not configured for your workspace. Configure SMTP or use the platform account before sending.',
    'broadcast_sms_not_configured_workspace' => 'SMS is not configured for your workspace. Configure an SMS provider or use the platform account before sending.',

];
