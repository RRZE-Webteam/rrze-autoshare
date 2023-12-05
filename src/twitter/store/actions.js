import {
    SET_AUTOSHARE_TWITTER_ENABLED,
    SET_ERROR_MESSAGE,
    SET_TWEET_TEXT,
    SET_SAVING,
    SET_LOADED,
    SET_OVERRIDING,
    SET_ALLOW_TWEET_IMAGE,
    SET_TWEET_ACCOUNTS,
} from "./constants";

export const setAutoshareEnabled = (autoshareEnabled) => ({
    type: SET_AUTOSHARE_TWITTER_ENABLED,
    autoshareEnabled,
});

export const setErrorMessage = (errorMessage) => ({
    type: SET_ERROR_MESSAGE,
    errorMessage,
});

export const setLoaded = () => ({
    type: SET_LOADED,
});

export const setOverriding = (overriding) => ({
    type: SET_OVERRIDING,
    overriding,
});

export const setSaving = (saving) => ({
    type: SET_SAVING,
    saving,
});

export const setTweetText = (tweetText) => ({
    type: SET_TWEET_TEXT,
    tweetText,
});

export const setAllowTweetImage = (allowTweetImage) => ({
    type: SET_ALLOW_TWEET_IMAGE,
    allowTweetImage,
});

export const setTweetAccounts = (tweetAccounts) => ({
    type: SET_TWEET_ACCOUNTS,
    tweetAccounts,
});
