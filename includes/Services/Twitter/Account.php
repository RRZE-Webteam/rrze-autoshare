<?php

namespace RRZE\Autoshare\Services\Twitter;

defined('ABSPATH') || exit;

use RRZE\Autoshare\Options\Encryption;
use function RRZE\Autoshare\settings;

class Account
{
    const TWITTER_ACCOUNT = 'rrze_autoshare_twitter_account';

    private $oauth;

    public function __construct()
    {
        $this->oauth = new OAuth();
    }

    /**
     * Inintialize the class and register the actions needed.
     */
    public function init()
    {
        add_action('admin_notices', [$this, 'connectionNotices']);
        add_action('admin_post_rrze_autoshare_twitter_authorize_action', [$this, 'authorize']);
        add_action('admin_post_rrze_autoshare_twitter_disconnect_action', [$this, 'disconnect']);
        add_action('admin_post_rrze_authoshare_authorize_callback', [$this, 'authorizeCallback']);
    }

    /**
     * Authorize the user.
     */
    public function authorize()
    {
        // Check if the user has the correct permissions.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rrze-autoshare'));
        }

        // Check if the nonce is valid.
        if (!isset($_GET['rrze_autoshare_twitter_authorize_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['rrze_autoshare_twitter_authorize_nonce'])), 'rrze_autoshare_twitter_authorize_action')) {
            wp_die(esc_html__('You are not authorized to perform this operation.', 'rrze-autoshare'));
        }

        // Get the request token.
        $callbackUrl = admin_url('admin-post.php?action=rrze_authoshare_authorize_callback');

        try {
            $requestToken = $this->oauth->requestToken($callbackUrl);

            // Save temporary OAuth tokens.
            $this->setOAuthTokens(get_current_user_id(), $requestToken['oauth_token'], $requestToken['oauth_token_secret']);

            // Initiate authorization.
            $url = $this->oauth->getAuthorizeUrl($requestToken['oauth_token']);
            if (!empty($url)) {
                wp_redirect($url);
                exit();
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $decoded = json_decode($e->getMessage());
            if (json_last_error() === JSON_ERROR_NONE) {
                $error = $decoded;
            }

            if (!empty($error->errors)) {
                $error = current($error->errors);
                $errorMessage = $error->message;
            } elseif (!empty($error)) {
                $errorMessage = $error;
            } else {
                $errorMessage = __('Something went wrong. Please try again.', 'rrze-autoshare');
            }

            $this->setConnectionNotice('error', $errorMessage);
        }

        // Redirect back to AutoShare settings page.
        wp_safe_redirect(admin_url('options-general.php?page=rrze_autoshare&tab=x-twitter'));
        exit();
    }

    /**
     * Callback for authorization process.
     *
     * @throws \Exception If Something went wrong.
     */
    public function authorizeCallback()
    {
        // Check if the user has the correct permissions.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rrze-autoshare'));
        }

        try {
            // Check if user has denied the authorization.
            $isDeniend = isset($_GET['denied']) ? sanitize_text_field(wp_unslash($_GET['denied'])) : false;
            if ($isDeniend) {
                throw new \Exception(esc_html__('Authorization denied for this request.', 'rrze-autoshare'));
            }

            // Get temporary OAuth tokens.
            $oauthTokens = $this->getOAuthTokens(get_current_user_id());
            $oauthToken = $oauthTokens['token'] ?? '';
            $oauthTokenSecret = $oauthTokens['token_secret'] ?? '';

            // Check if the request token is valid.
            if (!isset($_REQUEST['oauth_token']) || sanitize_text_field(wp_unslash($_REQUEST['oauth_token']) !== $oauthToken)) {
                throw new \Exception(esc_html__('Something went wrong. Please try again.', 'rrze-autoshare'));
            }

            // Get the access token.
            $oauthVerifier = isset($_REQUEST['oauth_verifier']) ? sanitize_text_field(wp_unslash($_REQUEST['oauth_verifier'])) : '';
            $accessToken = $this->oauth->getAccessToken($oauthToken, $oauthTokenSecret, $oauthVerifier);

            if (!$accessToken || !isset($accessToken['oauth_token']) || !isset($accessToken['oauth_token_secret'])) {
                throw new \Exception(esc_html__('Something went wrong while fetching the access token. Please try again.', 'rrze-autoshare'));
            }

            // Remove temporary credentials from cookies.
            $this->deleteOAuthTokens(get_current_user_id());

            // Get account details by access token.
            $account = $this->oauth->getAccountByToken($accessToken['oauth_token'], $accessToken['oauth_token_secret']);

            // Save account details.
            $this->saveAccount($account);
            $this->setConnectionNotice('success', esc_html__('X (Twitter) account authenticated successfully.', 'rrze-autoshare'));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->setConnectionNotice('error', $errorMessage);
        }

        // Redirect back to AutoShare settings page.
        wp_safe_redirect(admin_url('options-general.php?page=rrze_autoshare&tab=x-twitter'));
        exit();
    }

    /**
     * Disconnect X account.
     */
    public function disconnect()
    {
        // Check if the user has the correct permissions.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rrze-autoshare'));
        }

        // Check if the nonce is valid.
        if (!isset($_GET['rrze_autoshare_twitter_disconnect_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['rrze_autoshare_twitter_disconnect_nonce'])), 'rrze_autoshare_twitter_disconnect_action')) {
            wp_die(esc_html__('You are not authorized to perform this operation.', 'rrze-autoshare'));
        }

        try {
            if (empty($_GET['account_id'])) {
                throw new \Exception(esc_html__('X (Twitter) account ID is required to perform this operation.', 'rrze-autoshare'));
            }

            $accountId = sanitize_text_field(wp_unslash($_GET['account_id']));
            $oauth = new OAuth($accountId);
            $oauth->disconnectAccount();

            // Delete account details.
            $this->deleteAccount($accountId);

            $this->setConnectionNotice('success', __('X (Twitter) account disconnected successfully.', 'rrze-autoshare'));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->setConnectionNotice('error', $errorMessage);
        }

        // Redirect back to AutoShare settings page.
        wp_safe_redirect(admin_url('options-general.php?page=rrze_autoshare&tab=x-twitter'));
        exit();
    }

    /**
     * Show notices for connection errors/success.
     *
     * @return void
     */
    public function connectionNotices()
    {
        $notice = $this->getConnectionNotice();
        if (!$notice) {
            return;
        }

        if (!empty($notice['message'])) {
?>
            <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
<?php
        }
    }

    /**
     * Set connection notice.
     *
     * @param string $type Notice type.
     * @param string $message Notice message.
     */
    public function setConnectionNotice($type, $message)
    {
        set_transient(
            'rrze_autoshare_twitter_connection_notice',
            [
                'type'    => $type,
                'message' => $message,
            ],
            30
        );
    }

    /**
     * Get connection notice.
     */
    public function getConnectionNotice()
    {
        $notice = get_transient('rrze_autoshare_twitter_connection_notice');
        delete_transient('rrze_autoshare_twitter_connection_notice');

        return $notice;
    }

    /**
     * Set OAuth tokens.
     *
     * @param integer $userId Current user ID.
     * @param string $token OAuth token.
     * @param string $tokenSecret OAuth token secret.
     */
    private function setOAuthTokens(int $userId, string $token, string $tokenSecret)
    {
        $this->deleteOAuthTokens($userId);
        set_transient(
            md5('rrze_autoshare_twitter_oauth_tokens_' . $userId),
            [
                'token' => Encryption::encrypt($token),
                'token_secret' => Encryption::encrypt($tokenSecret),
            ],
            300
        );
    }

    /**
     * Get OAuth tokens.
     * 
     * @param integer $userId Current user ID.
     * @return array OAuth tokens
     */
    private function getOAuthTokens(int $userId)
    {
        $tokens = get_transient(
            md5('rrze_autoshare_twitter_oauth_tokens_' . $userId)
        );
        $token = $tokens['token'] ?? null;
        $tokenSecret = $tokens['token_secret'] ?? null;
        return [
            'token' => $token ? Encryption::decrypt($token) : '',
            'token_secret' => $tokenSecret ? Encryption::decrypt($tokenSecret) : '',
        ];
    }

    /**
     * Delete OAuth tokens.
     * 
     * @param integer $userId Current user ID.
     */
    private function deleteOAuthTokens(int $userId)
    {
        delete_transient(md5('rrze_autoshare_twitter_oauth_tokens_' . $userId));
    }

    /**
     * Save connected account details.
     *
     * @param array $account Account Data.
     * @return void
     */
    public function saveAccount($account)
    {
        $accounts = get_option(self::TWITTER_ACCOUNT, []);

        $accounts[$account['id']] = $account;
        update_option(self::TWITTER_ACCOUNT, $accounts);
    }

    /**
     * Delete connected account details.
     *
     * @param string $accountId Account ID.
     * @return void
     */
    public function deleteAccount($accountId)
    {
        $accounts = get_option(self::TWITTER_ACCOUNT, []);
        if (isset($accounts[$accountId])) {
            unset($accounts[$accountId]);
        }
        update_option(self::TWITTER_ACCOUNT, $accounts);
    }

    /**
     * Get connected account details.
     *
     * @param string $id Account ID.
     * @return array
     */
    public function getAccount($id)
    {
        $accounts = get_option(self::TWITTER_ACCOUNT, []);

        if (isset($accounts[$id])) {
            return $accounts[$id];
        }

        return [];
    }
}
