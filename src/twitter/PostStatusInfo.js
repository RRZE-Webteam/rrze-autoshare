import { __ } from "@wordpress/i18n";
import { compose } from "@wordpress/compose";
import { useState } from "@wordpress/element";
import { withSelect, useSelect, select } from "@wordpress/data";
import { Button, ToggleControl, Icon } from "@wordpress/components";
import { TweetTextField } from "./components/TweetTextField";
import { TwitterAccounts } from "./components/TwitterAccounts";
import {
    useHasFeaturedImage,
    useAllowTweetImage,
    useSaveTwitterData,
    useTweetText,
} from "./hooks";

import { StatusLogs } from "./components/StatusLogs";

export function PostStatusInfo() {
    const hasFeaturedImage = useHasFeaturedImage();
    const [allowTweetImage, setAllowTweetImage] = useAllowTweetImage();
    const [, setTweetText] = useTweetText();
    const [reTweet, setReTweet] = useState(false);
    const [tweetNow, setTweetNow] = useState(false);
    const { messages } = useSelect((__select) => {
        return {
            messages: __select("core/editor").getCurrentPostAttribute(
                "rrze_autoshare_twitter_status"
            ),
        };
    });

    const [statusMessages, setStatusMessages] = useState(messages);

    useSaveTwitterData();

    const reTweetHandler = async () => {
        setReTweet(true);

        const postId = await select("core/editor").getCurrentPostId();
        const body = new FormData();

        body.append("action", adminAutoshareTwitter.retweetAction);
        body.append("nonce", adminAutoshareTwitter.nonce);
        body.append("post_id", postId);

        const apiResponse = await fetch(ajaxurl, {
            method: "POST",
            body,
        });

        const { data } = await apiResponse.json();

        // Clear the tweet text if the tweet was successful.
        if (data.is_retweeted) {
            setTweetText("");
        }
        setStatusMessages(data);
        setReTweet(false);
    };

    if (statusMessages && !statusMessages.message.length) {
        return null;
    }

    const chevronUp = (
        <Icon
            icon={
                <svg
                    viewBox="0 0 28 28"
                    xmlns="http://www.w3.org/2000/svg"
                    width="28"
                    height="28"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path>
                </svg>
            }
        />
    );
    const chevronDown = (
        <Icon
            icon={
                <svg
                    viewBox="0 0 28 28"
                    xmlns="http://www.w3.org/2000/svg"
                    width="28"
                    height="28"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
                </svg>
            }
        />
    );

    return (
        <>
            <StatusLogs messages={statusMessages} />
            <Button
                className="rrze-autoshare-twitter-tweet-now"
                variant="link"
                text={__("Tweet now", "rrze-autoshare")}
                onClick={() => setTweetNow(!tweetNow)}
                iconPosition="right"
                icon={tweetNow ? chevronUp : chevronDown}
            />
            {tweetNow && (
                <>
                    {hasFeaturedImage && (
                        <ToggleControl
                            label={__(
                                "Use featured image in Tweet",
                                "rrze-autoshare"
                            )}
                            checked={allowTweetImage}
                            onChange={() => {
                                setAllowTweetImage(!allowTweetImage);
                            }}
                            className="rrze-autoshare-twitter-toggle-control"
                        />
                    )}
                    <TwitterAccounts />
                    <TweetTextField />
                    <Button
                        variant="primary"
                        className="rrze-autoshare-twitter-re-tweet"
                        text={
                            reTweet
                                ? __("Tweeting…", "rrze-autoshare")
                                : __("Tweet again", "rrze-autoshare")
                        }
                        onClick={() => {
                            reTweetHandler();
                        }}
                    />
                </>
            )}
        </>
    );
}

export default compose(
    withSelect((__select) => ({
        statusMessage: __select("core/editor").getCurrentPostAttribute(
            "rrze_autoshare_twitter_status"
        ),
    }))
)(PostStatusInfo);