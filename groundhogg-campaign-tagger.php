<?php
/*
Plugin Name: Groundhogg Campaign Tagger
Description: Automatically tags Mailgun emails with the campaign name from Groundhogg Broadcasts.
Version: 1.2.1
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

    // Add to headers
    $args['headers'][] = 'X-GH-Campaign-Name: ' . sanitize_text_field($campaign->name);

    return $args;

}, 10, 3);

add_filter('groundhogg/mailgun/send/api/builder', function($builder, $params, $headers, $to) {

    error_log('[GH Mailgun Builder] Params: ' . print_r($params, true));
    error_log('[GH Mailgun Builder] Headers: ' . print_r($headers, true));
    error_log('[GH Mailgun Builder] Builder: ' . print_r($builder, true));



    $contact_id = null;

    // Try to extract contact ID from headers
    if (is_array($headers)) {
        foreach ($headers as $header) {
            if (stripos($header, 'X-GH-Contact-ID:') === 0) {
                $contact_id = absint(trim(str_ireplace('X-GH-Contact-ID:', '', $header)));
                break;
            }
        }
    }

    if ($contact_id) {
        // Load the most recent email log for this contact
        $email_log = \Groundhogg\Plugin::instance()->dbs->get_db('email_logs')->query([
            'contact_id' => $contact_id,
            'orderby' => 'ID',
            'order' => 'DESC',
            'limit' => 1,
        ]);

        if (!empty($email_log)) {
            $log = $email_log[0];
            $email_id = absint($log->email_id);

            $email = new \Groundhogg\Email($email_id);
            $broadcast_id = absint($email->get_meta('_broadcast_id'));

            if ($broadcast_id) {
                $broadcast = new \Groundhogg\Broadcast($broadcast_id);
                $campaign_id = $broadcast->get_campaign_id();

                if ($campaign_id) {
                    $campaign = \Groundhogg\Plugin::instance()->dbs->get_db('campaigns')->get($campaign_id);

                    if ($campaign && !empty($campaign->name)) {
                        $builder->addTag(sanitize_text_field($campaign->name));
                    }
                }
            }
        }
    }

    // Always tag with "groundhogg"
    $builder->addTag('groundhogg');

    return $builder;

}, 10, 4);
