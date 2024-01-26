<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use function RRZE\Autoshare\settings;

class API
{
    public static function connect()
    {
        $account = new Account();
        $account->init();
    }

    /**
     * Handler for publishing to Twitter.
     *
     * @param int  $postId The current post ID.
     *
     * @return bool
     */
    public static function publishPost($postId)
    {
        $post = get_post($postId);
        if (!$post) {
            return false;
        }

        $tweet = Post::buildBody($post);

        $accounts = get_option(Account::TWITTER_ACCOUNT, []);
        if (empty($accounts)) {
            return false;
        }

        $accountId = array_key_first($accounts);

        try {
            $response = self::publishTweet($tweet, $post, $accountId);
            $response = self::validateResponse($response);
        } catch (\Exception $e) {
            $response = new \WP_Error(
                'rrze_autoshare_twitter_error',
                esc_html__('Something went wrong, please try again.', 'rrze_autoshare'),
                [
                    (object) ['message' => $e->getMessage()],
                ]
            );
        }

        if (!is_wp_error($response)) {
            delete_metadata($post->post_type, $postId, 'rrze_autoshare_twitter_error');
        }

        self::updateStatusMeta($post->post_type, $postId, $response);
    }

    /**
     * Publish a tweet.
     *
     * @param string $body The tweet body.
     * @param \WP_Post $post The post object.
     * @param int|null $accountId The Twitter account ID.
     *
     * @return object
     */
    private static function publishTweet($body, $post, $accountId = null)
    {
        $oauth = new OAuth($accountId);

        if (empty($body)) {
            return;
        }

        $update_data = array(
            'text' => $body,
        );

        if (settings()->getOption('twitter_featured_image')) {
            $image = Media::getFeaturedImageFile($post);
            $response = $oauth->uploadMedia($image);

            if (!is_object($response) || !isset($response->media_id)) {
                $mediaId = 0;
            } else {
                $mediaId = $response->media_id;
            }
            if ($mediaId) {
                $update_data['media'] = [
                    'media_ids' => [(string) $mediaId],
                ];
            }
        }

        // Send tweet to Twitter.
        $response = $oauth->tweet($update_data);

        return $response;
    }

    /**
     * Validate and build response message.
     *
     * @param object $response The api response to validate.
     *
     * @return mixed
     */
    private static function validateResponse($response)
    {
        if (!empty($response->id)) {
            $validatedResponse = [
                'id' => $response->id,
                // Twitter API v2 doesn't return created_at.
                'created_at' => gmdate('c'),
            ];
        } else {
            $errors = $response->errors;
            if (empty($response->errors) && !empty($response->detail)) {
                $errors = [
                    (object) [
                        'code' => $response->status,
                        'message' => $response->detail,
                    ],
                ];
            }
            $validatedResponse = new \WP_Error(
                'rrze_autoshare_twitter_error',
                __('Something happened during Twitter update.', 'rrze-autoshare'),
                $errors
            );
        }

        return $validatedResponse;
    }

    /**
     * Add validated response as post meta.
     *
     * @param $postType The post type.
     * @param int $postId The post id.
     * @param object $data The tweet request data.
     */
    private static function updateStatusMeta($postType, $postId, $data)
    {
        if (!is_wp_error($data)) {
            $status = 'published';
            $response = [
                'status' => $status,
                'twitter_id' => (int) $data['id'],
                'created_at' => sanitize_text_field($data['created_at']),
            ];
        } elseif (is_wp_error($data)) {
            $errorMessage = $data->error_data['rrze_autoshare_twitter_error'][0];
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
                'message' => __('This post was not published on X.', 'rrze-autoshare'),
            ];
        }
        update_metadata($postType, $postId, sprintf('rrze_autoshare_twitter_%s', $status), $response);
    }

    public static function isConnected()
    {
        return (bool) get_option(Account::TWITTER_ACCOUNT);
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
            __('You’ve authorized Autoshare to read and write to the X timeline.', 'rrze-autoshare') :
            __('Authorize Autoshare to read and write to the X timeline.', 'rrze-autoshare');
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
                    'action' => 'rrze_autoshare_twitter_authorize_action'
                ],
                admin_url('admin-post.php')
            ),
            'rrze_autoshare_twitter_authorize_action',
            'rrze_autoshare_twitter_authorize_nonce'
        );
    }

    private static function revokeUrl()
    {
        $accounts = get_option(Account::TWITTER_ACCOUNT, []);
        $accountId = array_key_first($accounts);
        return wp_nonce_url(
            add_query_arg(
                [
                    'account_id' => $accountId,
                    'action' => 'rrze_autoshare_twitter_disconnect_action'
                ],
                admin_url('admin-post.php')
            ),
            'rrze_autoshare_twitter_disconnect_action',
            'rrze_autoshare_twitter_disconnect_nonce'
        );
    }
}
