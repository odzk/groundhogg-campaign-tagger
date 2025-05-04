<?php
/*
Plugin Name: Groundhogg Campaign Tagger
Description: Automatically tags Mailgun emails with the campaign name from Groundhogg Broadcasts.
Version: 1.2
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

// Use the builder hook to add multiple tags
add_filter('groundhogg/mailgun/send/api/builder', function($builder, $params, $headers, $to) {

    // Try to extract campaign name from the email object via broadcast meta
    if (!empty($params['email_id'])) {
        $email = new \Groundhogg\Email($params['email_id']);
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

    // Always add fallback/default tag
    $builder->addTag('Campaign Test');

    return $builder;

}, 10, 4);