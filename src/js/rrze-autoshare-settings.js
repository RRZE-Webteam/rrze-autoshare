/* global autoshareSettingsObject, wp */

(function initializeTransmissionTestPostSearch(wordpress) {
    'use strict';

    if (!wordpress || !wordpress.apiFetch || !autoshareSettingsObject) {
        return;
    }

    var searchInput = document.getElementById(autoshareSettingsObject.searchInputId);
    var postIdInput = document.getElementById(autoshareSettingsObject.postIdInputId);
    var resultsElement = document.getElementById(autoshareSettingsObject.resultsId);
    var statusElement = document.getElementById(autoshareSettingsObject.statusId);
    var searchTimeout;
    var postsById = {};

    if (!searchInput || !postIdInput || !resultsElement || !statusElement) {
        return;
    }

    function clearResults() {
        resultsElement.replaceChildren();
        postsById = {};
    }

    function selectPost(event) {
        var postId = event.currentTarget.dataset.postId;
        var post = postsById[postId];

        if (!post) {
            return;
        }

        postIdInput.value = post.id;
        searchInput.value = post.title;
        statusElement.textContent = autoshareSettingsObject.selectedPost + ' ' + post.title + ' (#' + post.id + ')';
        clearResults();
    }

    function renderResults(posts) {
        var i;

        clearResults();
        if (!posts.length) {
            statusElement.textContent = autoshareSettingsObject.noResults;
            return;
        }

        for (i = 0; i < posts.length; i++) {
            var post = posts[i];
            var resultButton = document.createElement('button');

            postsById[post.id] = post;
            resultButton.type = 'button';
            resultButton.className = 'button-link rrze-autoshare-post-search-result';
            resultButton.dataset.postId = post.id;
            resultButton.textContent = post.title + ' (#' + post.id + ')';
            resultButton.setAttribute('role', 'option');
            resultButton.addEventListener('click', selectPost);
            resultsElement.appendChild(resultButton);
        }
    }

    function handleSearchSuccess(posts) {
        renderResults(Array.isArray(posts) ? posts : []);
    }

    function handleSearchFailure() {
        clearResults();
        statusElement.textContent = autoshareSettingsObject.searchFailed;
    }

    function searchPosts(searchTerm) {
        var query = new URLSearchParams({
            search: searchTerm,
            per_page: autoshareSettingsObject.searchLimit,
            type: autoshareSettingsObject.searchType,
            subtype: autoshareSettingsObject.searchSubtype
        });

        wordpress.apiFetch({
            path: autoshareSettingsObject.searchEndpoint + '?' + query.toString()
        }).then(handleSearchSuccess).catch(handleSearchFailure);
    }

    function runSearch() {
        var searchTerm = searchInput.value.trim();

        if (searchTerm.length < autoshareSettingsObject.searchMinimumLength) {
            clearResults();
            return;
        }

        searchPosts(searchTerm);
    }

    function handleSearchInput() {
        postIdInput.value = '';
        statusElement.textContent = '';
        window.clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(runSearch, 250);
    }

    searchInput.addEventListener('input', handleSearchInput);
}(window.wp));
