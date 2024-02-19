<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Encryption;
use Abraham\TwitterOAuth\TwitterOAuth as TwitterOAuth;
use function RRZE\Autoshare\settings;

class OAuth
{
    /**
     * The consumer key.
     *
     * @var string The consumer key.
     */
    protected $consumerKey;

    /**
     * The consumer secret.
     *
     * @var string The consumer secret.
     */
    protected $consumerSecret;

    /**
     * The access token.
     *
     * @var string The access token.
     */
    protected $accessToken;

    /**
     * The access secret.
     *
     * @var string The access secret.
     */
    protected $accessTokenSecret;

    /**
     * The TwitterOAuth client.
     *
     * @var TwitterOAuth The TwitterOAuth client.
     */
    protected $client;

    /**
     * Construct the OAuth class.
     *
     * @param string $accountId The Twitter account ID.
     */
    public function __construct($accountId = null)
    {
        $this->consumerKey = Encryption::decrypt(settings()->getOption('twitter_api_key'));
        $this->consumerSecret = Encryption::decrypt(settings()->getOption('twitter_api_key_secret'));

        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            return;
        }

        $this->client = new TwitterOAuth(
            $this->consumerKey,
            $this->consumerSecret
        );

        if (!empty($accountId)) {
            $accounts = new Account();
            $account = $accounts->getAccount($accountId);
            if (!empty($account)) {
                $this->accessToken = $account['oauth_token'];
                $this->accessTokenSecret = $account['oauth_token_secret'];

                $this->client = new TwitterOAuth(
                    $this->consumerKey,
                    $this->consumerSecret,
                    $this->accessToken,
                    $this->accessTokenSecret
                );
            }
        }
    }

    /**
     *  Initialize the OAuth client.
     */
    public function initClient()
    {
        $this->client = new TwitterOAuth(
            $this->consumerKey,
            $this->consumerSecret,
            $this->accessToken,
            $this->accessTokenSecret
        );
    }

    /**
     * Request the OAuth token for initite authorization.
     *
     * @param string $callbackUrl The callback URL.
     * @return array
     */
    public function requestToken($callbackUrl)
    {
        return $this->client->oauth('oauth/request_token', ['oauth_callback' => $callbackUrl]);
    }

    /**
     * Get the authorize URL.
     *
     * @param string $oauthToken The OAuth token.
     * @return string
     */
    public function getAuthorizeUrl($oauthToken)
    {
        return $this->client->url('oauth/authorize', ['oauth_token' => $oauthToken]);
    }

    /**
     * Get the OAuth access token.
     *
     * @param string $oauthToken The OAuth token returned from the authorization step.
     * @param string $oauthTokenSecret The OAuth token secret returned from the authorization step.
     * @param string $oauthVerifier The OAuth verifier returned from the authorization step.
     * @return array
     */
    public function getAccessToken($oauthToken, $oauthTokenSecret, $oauthVerifier)
    {
        $this->accessToken = $oauthToken;
        $this->accessTokenSecret = $oauthTokenSecret;

        $this->initClient();

        return $this->client->oauth('oauth/access_token', ['oauth_verifier' => $oauthVerifier]);
    }

    /**
     * Get Twitter account by access token and access token secret.
     *
     * @param string $access_token The access token.
     * @param string $access_token_secret The access token secret.
     * @return array|\WP_Error
     */
    public function getAccountByToken($access_token, $access_token_secret)
    {
        $this->accessToken = $access_token;
        $this->accessTokenSecret = $access_token_secret;

        // Init Twitter client.
        $this->initClient();

        return $this->getCurrentAccount();
    }

    /**
     * Get the Twitter current account.
     *
     * @return array|\WP_Error
     */
    public function getCurrentAccount()
    {
        $this->client->setApiVersion('2');
        $user = $this->client->get(
            'users/me',
            [
                'user.fields' => 'id,name,username,profile_image_url',
            ]
        );

        if (!$user || !isset($user->data) || !isset($user->data->id)) {
            if (!empty($user->detail)) {
                return new \WP_Error('error_get_twitter_user', $user->detail);
            }
            return new \WP_Error('error_get_twitter_user', __('Something went wrong during getting user details.', 'rrze-autoshare'));
        }

        $user_data = $user->data;
        return [
            'id' => $user_data->id,
            'name' => $user_data->name,
            'username' => $user_data->username,
            'profile_image_url' => $user_data->profile_image_url,
            'oauth_token' => $this->accessToken,
            'oauth_token_secret' => $this->accessTokenSecret,
        ];
    }

    /**
     * Send tweet to Twitter.
     *
     * @param array $data Tweet data.
     * @return object
     */
    public function tweet($data)
    {
        $this->client->setTimeouts(10, 30);
        $this->client->setApiVersion('2');
        $response = $this->client->post(
            'tweets',
            $data,
            true
        );

        // Twitter API V2 wraps response in data.
        if (isset($response->data)) {
            $response = $response->data;
        }

        return $response;
    }

    /**
     * Upload media to Twitter.
     *
     * @param string $image The path to the image file.
     * @return object
     */
    public function uploadMedia($image)
    {
        $this->client->setTimeouts(10, 60);
        $this->client->setApiVersion('1.1');
        return $this->client->upload('media/upload', ['media' => $image]);
    }

    /**
     * Disconnect Twitter account.
     *
     * @return bool True if account was disconnected, false otherwise.
     */
    public function disconnectAccount()
    {
        try {
            $this->client->oauth('1.1/oauth/invalidate_token');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
