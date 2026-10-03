<?php // TwittPay Settings

Redux::setSection($opt_name, [
    'title'      => __('TwittPay', 'wpjobster'),
    'desc'       => __('TwittPay Settings', 'wpjobster'),
    'id'         => 'twittpay-settings',
    'subsection' => true,
    'fields'     => array_merge(
        wpj_get_gateway_default_fields(
            [
                'gateway_id'      => 'twittpay',
                'gateway_name'    => 'TwittPay',
                'gateway_version' => '1.0',
                'license'         => false,
                'public_key'      => false,
                'secret_key'      => false,
                'new_fields'      => [
                    [
                        'unique_id' => 'twittpay-settings-section',
                        'type'      => 'section',
                        'title'     => esc_html__('Gateway Settings', 'wpjobster'),
                        'indent'    => true,
                    ],
                    [
                        'unique_id' => 'wpjobster_twittpay_api_key',
                        'type'      => 'text',
                        'title'     => __('Brand Key', 'wpjobster'),
                        'subtitle'  => __('Copy it from your TwittPay dashboard under Brands.', 'wpjobster'),
                        'default'   => false,
                    ],
                ],
            ]
        ),
    ),
]);

do_action('wpj_after_admin_twittpay_settings_fields');
