<?php
/*
Plugin Name: Groundhogg Campaign Tagger
Description: Automatically tags Mailgun emails with the campaign name from Groundhogg Broadcasts.
Version: 1.2.4
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
    if (!$broadcast_id) {
        error_log('[GH Campaign Tagger] No broadcast ID found for email.');
        return $args;
    }

    $broadcast = new Broadcast($broadcast_id);
    $campaign_id = $broadcast->get_campaign_id();

    if (!$campaign_id) {
        error_log('[GH Campaign Tagger] No campaign ID found for broadcast ID: ' . $broadcast_id);
        return $args;
    }

    $campaign = Plugin::instance()->dbs->get_db('campaigns')->get($campaign_id);

    if (!$campaign || empty($campaign->name)) {
        error_log('[GH Campaign Tagger] No campaign or campaign name found for campaign ID: ' . $campaign_id);
        return $args;
    }

    // Add campaign name to headers
    $campaign_name = sanitize_text_field($campaign->name);
    $args['headers'][] = 'X-GH-Campaign-Name: ' . $campaign_name;
    error_log('[GH Campaign Tagger] Added header X-GH-Campaign-Name: ' . $campaign_name);

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
                error_log('[GH Mailgun Builder] Found campaign name in headers: ' . $campaign_name);
                break;
            }
        }
    } else {
        error_log('[GH Mailgun Builder] Headers is not an array: ' . print_r($headers, true));
    }

    // Add campaign name as tag if found
    if ($campaign_name) {
        $sanitized_campaign_name = sanitize_text_field($campaign_name);
        $builder->addTag($sanitized_campaign_name);
        error_log('[GH Mailgun Builder] Added tag: ' . $sanitized_campaign_name);
    } else {
        error_log('[GH Mailgun Builder] No campaign name found in headers.');
    }

    // Always tag with "groundhogg"
    $builder->addTag('groundhogg');
    error_log('[GH Mailgun Builder] Added default tag: groundhogg');

    return $builder;

}, 10, 4);