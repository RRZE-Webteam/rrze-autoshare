import { Component } from "@wordpress/element";
import { registerPlugin } from "@wordpress/plugins";
import {
    PluginPrePublishPanel,
    PluginPostPublishPanel,
    PluginDocumentSettingPanel,
} from "@wordpress/edit-post";
import { dispatch, select, subscribe } from "@wordpress/data";
import { Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

import { createAutoshareStore, STORE } from "./store";
import { getIconByStatus } from "./utils";
import PrePublishPanel from "./PrePublishPanel";
import PostStatusInfo from "./PostStatusInfo";

import { EnabledIcon, DisabledIcon } from "./components/PluginIcon";

createAutoshareStore();

class PrePublishPanelPlugin extends Component {
    constructor(props) {
        super(props);

        this.state = {
            enabledText: "",
        };

        this.maybeSetEnabledText = this.maybeSetEnabledText.bind(this);
    }

    componentDidMount() {
        dispatch(STORE).setLoaded();
        subscribe(this.maybeSetEnabledText);
    }

    maybeSetEnabledText() {
        try {
            const enabled = select(STORE).getAutoshareEnabled();
            const enabledText = enabled
                ? __("This post will be Tweeted", "rrze-autoshare")
                : __("This post will not be Tweeted", "rrze-autoshare");

            if (enabledText !== this.state.enabledText) {
                this.setState({ enabled, enabledText });
            }
        } catch (e) {}
    }

    render() {
        const { enabled, enabledText } = this.state;
        const PluginIcon = enabled ? EnabledIcon : DisabledIcon;
        const AutoTweetIcon = (
            <Icon
                className="rrze-autoshare-twitter-icon components-panel__icon"
                icon={PluginIcon}
                size={24}
            />
        );

        return (
            <PluginPrePublishPanel
                title={enabledText}
                icon={AutoTweetIcon}
                className="rrze-autoshare-twitter-pre-publish-panel"
            >
                <PrePublishPanel />
            </PluginPrePublishPanel>
        );
    }
}

const PostPublishPanelPlugin = () => {
    return (
        <PluginPostPublishPanel className="rrze-autoshare-twitter-post-status-info">
            <PostStatusInfo />
        </PluginPostPublishPanel>
    );
};

class EditorPanelPlugin extends Component {
    constructor(props) {
        super(props);

        this.state = {
            enabledText: "",
        };

        this.maybeSetEnabledText = this.maybeSetEnabledText.bind(this);
    }

    componentDidMount() {
        dispatch(STORE).setLoaded();
        subscribe(this.maybeSetEnabledText);
    }

    maybeSetEnabledText() {
        try {
            const enabled = select(STORE).getAutoshareEnabled();
            const enabledText = enabled
                ? __("Autotweet enabled", "rrze-autoshare")
                : __("Autotweet disabled", "rrze-autoshare");

            if (enabledText !== this.state.enabledText) {
                this.setState({ enabledText, enabled });
            }
        } catch (e) {}
    }

    render() {
        const postStatus =
            select("core/editor").getCurrentPostAttribute("status");
        if ("publish" === postStatus) {
            const tweetMeta = select("core/editor").getCurrentPostAttribute(
                "rrze_autoshare_twitter_status"
            );
            let tweetStatus = "";
            if (tweetMeta && tweetMeta.message && tweetMeta.message.length) {
                tweetStatus =
                    tweetMeta.message[tweetMeta.message.length - 1].status ||
                    "";
            }

            return (
                <PluginDocumentSettingPanel
                    title={__("Autotweet", "rrze-autoshare")}
                    icon={getIconByStatus(tweetStatus)}
                    className="rrze-autoshare-twitter-editor-panel"
                >
                    <PostStatusInfo />
                </PluginDocumentSettingPanel>
            );
        }

        const { enabled, enabledText } = this.state;
        const PluginIcon = enabled ? EnabledIcon : DisabledIcon;
        const AutoTweetIcon = (
            <Icon
                className="rrze-autoshare-twitter-icon components-panel__icon"
                icon={PluginIcon}
                size={24}
            />
        );

        return (
            <PluginDocumentSettingPanel
                title={enabledText}
                icon={AutoTweetIcon}
                className="rrze-autoshare-editor-panel"
            >
                <PrePublishPanel />
            </PluginDocumentSettingPanel>
        );
    }
}

registerPlugin("rrze-autoshare-twitter-editor-panel", {
    render: EditorPanelPlugin,
});
registerPlugin("rrze-autoshare-twitter-pre-publish-panel", {
    render: PrePublishPanelPlugin,
});
registerPlugin("rrze-autoshare-twitter-post-publish-panel", {
    render: PostPublishPanelPlugin,
});
