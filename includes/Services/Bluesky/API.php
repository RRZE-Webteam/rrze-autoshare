<?php

namespace RRZE\Autoshare\Services\Bluesky;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Encryption;
use function RRZE\Autoshare\plugin;
use function RRZE\Autoshare\settings;

class API
{
    const ACCESS_JWT = 'rrze_autoshare_bluesky_access_jwt';

    const REFRESH_JWT = 'rrze_autoshare_bluesky_refresh_jwt';

    const DID = 'rrze_autoshare_bluesky_did';

    public static function connect()
    {
        $host = settings()->getOption('bluesky_domain');
        $identifier = settings()->getOption('bluesky_identifier');
        $password = settings()->getOption('bluesky_password');

        if (!$host || !$identifier || !$password) {
            return false;
        }

        if (
            isset($_GET['action']) &&
            'authorize' === $_GET['action'] &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'rrze-autoshare-bluesky-authorize')
        ) {
            if (!self::authorizeAccess($host, $identifier, $password)) {
                self::revokeAccess();
            }
        } elseif (
            isset($_GET['action']) &&
            'revoke' === $_GET['action'] &&
            isset($_GET['_wpnonce']) &&
            wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'rrze-autoshare-bluesky-revoke')
        ) {
            self::revokeAccess();
        } else {
            // Refresh token
            self::authorizeAccess($host, $identifier, $password);
        }
    }

    private static function authorizeAccess($host, $identifier, $password)
    {
        $host = trailingslashit($host);
        $password = Encryption::decrypt($password);
        $wpVersion = get_bloginfo('version');
        $pluginVersion = plugin()->getVersion();
        $userAgent = 'WordPress/' . $wpVersion . '; ' . get_bloginfo('url');

        $response = wp_safe_remote_post(
            esc_url_raw($host . 'xrpc/com.atproto.server.createSession'),
            [
                'user-agent' => "$userAgent; RRZE-Autoshare/$pluginVersion",
                'headers'    => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode(
                    [
                        'identifier' => $identifier,
                        'password'   => $password,
                    ]
                ),
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) >= 300
        ) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (
            empty($data['accessJwt']) ||
            empty($data['refreshJwt']) ||
            empty($data['did'])
        ) {
            self::revokeAccess();
            return false;
        }

        update_option(self::ACCESS_JWT, $data['accessJwt']);
        update_option(self::REFRESH_JWT, $data['refreshJwt']);
        update_option(self::DID, $data['did']);
        return true;
    }

    private static function revokeAccess()
    {
        delete_option(self::ACCESS_JWT);
        delete_option(self::REFRESH_JWT);
        delete_option(self::DID);
        return;
    }

    public static function refreshToken()
    {
        self::connect();
    }

    public static function publishPost($postId)
    {
        $post = get_post($postId);

        $locale = get_locale();
        $langCode = substr($locale, 0, 2);
        $title = sanitize_text_field($post->post_title);
        $text = Post::getContent($post);
        if (empty($text)) {
            return;
        }

        $record = [
            '$type'     => 'app.bsky.feed.post',
            'text'      => $text,
            'langs'     => [$langCode],
            'createdAt' => gmdate('c', strtotime($post->post_date_gmt))
        ];

        $links = self::getLinks($text);
        if (!empty($links)) {
            $record = array_merge($record, $links);
        }

        $media = Media::getImages($post);
        if (!empty($media)) {
            $count = 1;
            $media = array_slice($media, 0, $count, true);

            foreach ($media as $id => $alt) {
                $image = Media::uploadImage($id, $alt);
            }
        }
        if (!empty($image['blob'])) {
            $embed = [
                'embed' => [
                    '$type' => 'app.bsky.embed.images',
                    'images' => [
                        [
                            'alt' => $title,
                            'image' => $image['blob']
                        ],
                    ],
                ]
            ];
            $record = array_merge($record, $embed);
        }

        $accessToken = get_option(self::ACCESS_JWT);
        $host = settings()->getOption('bluesky_domain');
        $did = get_option(self::DID);

        $host = trailingslashit($host);

        $wpVersion = get_bloginfo('version');
        $pluginVersion = plugin()->getVersion();
        $userAgent = 'WordPress/' . $wpVersion . '; ' . get_bloginfo('url');

        $response = wp_safe_remote_post(
            esc_url_raw($host . 'xrpc/com.atproto.repo.createRecord'),
            [
                'user-agent' => "$userAgent; RRZE-Autoshare/$pluginVersion",
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'body' => wp_json_encode(
                    [
                        'collection' => 'app.bsky.feed.post',
                        'did'        => esc_html($did),
                        'repo'       => esc_html($did),
                        'record'     => $record,
                    ]
                ),
            ]
        );

        $response = self::validateResponse($response);

        self::updateStatusMeta($post->post_type, $postId, $response);
    }

    private static function getLinks($text)
    {
        $urls = self::getUrlsFromText($text);
        $links = [];
        if (!empty($urls)) {
            foreach ($urls as $url) {
                $a = [
                    "index" => [
                        "byteStart" => $url['start'],
                        "byteEnd" => $url['end'],
                    ],
                    "features" => [
                        [
                            '$type' => "app.bsky.richtext.facet#link",
                            'uri' => $url['url'],
                        ],
                    ],
                ];

                $links[] = $a;
            }
            $links = [
                'facets' =>
                $links,
            ];
        }

        return $links;
    }

    private static function getUrlsFromText($text)
    {
        $regex = '/(https?:\/\/[^\s]+)/';
        preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE);

        $urlData = [];

        foreach ($matches[0] as $match) {
            $url = $match[0];
            $start = $match[1];
            $end = $start + strlen($url);

            $urlData[] = [
                'start' => $start,
                'end' => $end,
                'url' => $url,
            ];
        }

        return $urlData;
    }

    private static function validateResponse($response)
    {
        if (!is_wp_error($response)) {
            $body = json_decode($response['body']);
        }

        if (!empty($body->uri)) {
            $validatedResponse = [
                'id' => $body->uri,
                'created_at' => $body->created_at ?? gmdate('c'),
            ];
        } else {
            $code = is_wp_error($response) ? '500' : wp_remote_retrieve_response_code($response);
            $message = is_wp_error($response) ? $response->get_error_message() : $body->error;
            $errors = [
                (object) [
                    'code' => sanitize_text_field($code),
                    'message' => sanitize_text_field($message),
                ],
            ];
            $validatedResponse = new \WP_Error(
                'rrze_autoshare_bluesky_error',
                __('An error occurred while trying to publish.', 'rrze-autoshare'),
                $errors
            );
        }

        return $validatedResponse;
    }

    private static function updateStatusMeta($postType, $postId, $data)
    {
        if (!is_wp_error($data)) {
            $status = 'published';
            $response = [
                'status' => $status,
                'bluesky_id' => sanitize_text_field($data['id']),
                'created_at' => sanitize_text_field($data['created_at']),
            ];
        } elseif (is_wp_error($data)) {
            $errorMessage = $data->error_data['rrze_autoshare_bluesky_error'][0];
            // translators: %d is the error code.
            $errorCodeText = $errorMessage->code ? sprintf(__('Error: %d. ', 'rrze-autoshare'), $errorMessage->code) : '';
            $status = 'error';
            $response = [
                'status'  => $status,
                'message' => sanitize_text_field($errorCodeText . $errorMessage->message),
            ];
        } else {
            $status = 'unknown';
            $response = [
                'status'  => $status,
                'message' => __('This post was not published on Bluesky.', 'rrze-autoshare'),
            ];
        }

        update_metadata($postType, $postId, sprintf('rrze_autoshare_bluesky_%s', $status), $response);
    }

    public static function isConnected()
    {
        return (bool) get_option(self::ACCESS_JWT);
    }

    public static function authorizeAccessText()
    {
        return self::isConnected() ?
            __('Revoke Access', 'rrze-autoshare') :
            __('Authorize Access', 'rrze-autoshare');
    }

    public static function authorizeAccessDescription()
    {
        return self::isConnected() ?
            __('You’ve authorized Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare') :
            __('Authorize Autoshare to read and write to the Bluesky timeline.', 'rrze-autoshare');
    }

    public static function authorizeAccessUrl()
    {
        if (self::isConnected()) {
            return self::revokeUrl();
        } else {
            return self::authorizeUrl();
        }
    }

    private static function authorizeUrl()
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'rrze_autoshare',
                    'tab'  => 'bluesky',
                    'action' => 'authorize'
                ],
                admin_url('options-general.php')
            ),
            'rrze-autoshare-bluesky-authorize',
            '_wpnonce'
        );
    }

    private static function revokeUrl()
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'rrze_autoshare',
                    'tab'  => 'bluesky',
                    'action' => 'revoke'
                ],
                admin_url('options-general.php')
            ),
            'rrze-autoshare-bluesky-revoke',
            '_wpnonce'
        );
    }
}
