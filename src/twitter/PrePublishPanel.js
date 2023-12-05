import { Button, ToggleControl } from "@wordpress/components";
import { select } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import { TweetTextField } from "./components/TweetTextField";
import {
    useTwitterAutoshareEnabled,
    useTwitterTextOverriding,
    useAllowTweetImage,
    useTwitterAutoshareErrorMessage,
    useSaveTwitterData,
    useHasFeaturedImage,
} from "./hooks";
import { TwitterAccounts } from "./components/TwitterAccounts";
import { StatusLogs } from "./components/StatusLogs";

export default function PrePublishPanel() {
    const [autoshareEnabled, setAutoshareEnabled] =
        useTwitterAutoshareEnabled();
    const [overriding, setOverriding] = useTwitterTextOverriding();
    const [allowTweetImage, setAllowTweetImage] = useAllowTweetImage();
    const [errorMessage] = useTwitterAutoshareErrorMessage();
    const hasFeaturedImage = useHasFeaturedImage();

    const messages = select("core/editor").getCurrentPostAttribute(
        "rrze_autoshare_twitter_status"
    );
    useSaveTwitterData();

    return (
        <>
            <StatusLogs messages={messages} />
            <ToggleControl
                label={
                    autoshareEnabled
                        ? __("Tweet when published", "rrze-autoshare")
                        : __("Don't Tweet", "rrze-autoshare")
                }
                checked={autoshareEnabled}
                onChange={(checked) => {
                    setAutoshareEnabled(checked);
                }}
                className="rrze-autoshare-twitter-toggle-control"
            />

            {autoshareEnabled && hasFeaturedImage && (
                <ToggleControl
                    label={__("Use featured image in Tweet", "rrze-autoshare")}
                    checked={allowTweetImage}
                    onChange={() => {
                        setAllowTweetImage(!allowTweetImage);
                    }}
                    className="rrze-autoshare-twitter-toggle-control"
                />
            )}

            {autoshareEnabled && <TwitterAccounts />}

            {autoshareEnabled && (
                <div className="rrze-autoshare-twitter-prepublish__override-row">
                    {overriding && <TweetTextField />}

                    <Button
                        isLink
                        onClick={() => {
                            setOverriding(!overriding);
                        }}
                    >
                        {overriding
                            ? __("Hide", "rrze-autoshare")
                            : __("Edit", "rrze-autoshare")}
                    </Button>
                </div>
            )}
            <div>{errorMessage}</div>
        </>
    );
}
