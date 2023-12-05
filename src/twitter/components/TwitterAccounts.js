import { ToggleControl, ExternalLink } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

import { useTweetAccounts } from "../hooks";
const { connectedAccounts, connectAccountUrl } = adminAutoshareTwitter;

/**
 * Twitter accounts component.
 *
 * @return {Function} Twitter accounts component.
 */
export function TwitterAccounts() {
    const accounts = connectedAccounts ? Object.values(connectedAccounts) : [];

    return (
        <div className="rrze-autoshare-twitter-accounts-wrapper">
            {accounts.map((account) => (
                <TwitterAccount key={account.id} {...account} />
            ))}
            <span className="connect-account-link">
                <ExternalLink href={connectAccountUrl}>
                    {__("Connect an account", "rrze-autoshare")}
                </ExternalLink>
            </span>
        </div>
    );
}

/**
 * Twitter account component.
 *
 * @param {Object} props Twitter account props.
 *
 * @return {Function} Twitter account component.
 */
function TwitterAccount(props) {
    const [tweetAccounts, setTweetAccounts] = useTweetAccounts();
    const { id, name, username, profile_image_url: profileUrl } = props;
    return (
        <div className="twitter-account-wrapper">
            <img
                src={profileUrl}
                alt={name}
                className="twitter-account-profile-image"
            />
            <span className="account-details">
                <strong>@{username}</strong>
                <br />
                {name}
            </span>
            <ToggleControl
                checked={tweetAccounts && tweetAccounts.includes(id)}
                onChange={(checked) => {
                    if (checked) {
                        setTweetAccounts([...tweetAccounts, id]);
                    } else {
                        setTweetAccounts(
                            tweetAccounts.filter((account) => account !== id)
                        );
                    }
                }}
                className="rrze-autoshare-twitter-account-toggle"
            />
        </div>
    );
}