<?php
/*
Plugin Name: Groundhogg Campaign Tagger
Description: Automatically tags Mailgun emails with the campaign name from Groundhogg Broadcasts.
Version: 1.2.3
Author: Odysseus Ambut
Author URI: https://web-mech.net
GitHub Plugin URI: https://github.com/odzk/groundhogg-campaign-tagger
Primary Branch: main
*/

use Groundhogg\Plugin;
use Groundhogg\Broadcast;
use Groundhogg\Email;

if (!defined('ABSPATH')) exit;

// Inject the campaign name into the email headers before Mailgun sends it
add_filter('groundhogg/send_email/wp_mail_args', function ($args, $email, $contact) {

    // Only continue if it's a broadcast
    $broadcast_id = absint($email->get_meta('_broadcast_id'));
    if (!$broadcast_id) return $args;

    $broadcast = new Broadcast($broadcast_id);
    $campaign_id = $broadcast->get_campaign_id();

    if (!$campaign_id) return $args;

    $campaign = Plugin::instance()->dbs->get_db('campaigns')->get($campaign_id);

    if (!$campaign || empty($campaign->name)) return $args;

    // Add campaign name to headers
    $args['headers'][] = 'X-GH-Campaign-Name: ' . sanitize_text_field($campaign->name);

    return $args;

}, 10, 3);

// Add campaign name as a tag in Mailgun
add_filter('groundhogg/mailgun/send/api/builder', function ($builder, $params, $headers, $to) {

    // Debugging logs
    error_log('[GH Mailgun Builder] Params: ' . print_r($params, true));
    error_log('[GH Mailgun Builder] Headers: ' . print_r($headers, true));
    error_log('[GH Mailgun Builder] Builder: ' . print_r($builder, true));

    // Extract campaign name from headers
    $campaign_name = null;
    if (is_array($headers)) {
        foreach ($headers as $header) {
            if (stripos($header, 'X-GH-Campaign-Name:') === 0) {
                $campaign_name = trim(str_ireplace('X-GH-Campaign-Name:', '', $header));
                break;
            }
        }
    }

    // Add campaign name as tag if found
    if ($campaign_name) {
        $builder->addTag(sanitize_text_field($campaign_name));
    }

    // Always tag with "groundhogg"
    $builder->addTag('groundhogg');

    return $builder;

}, 10, 4);