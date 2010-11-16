/**
 * (c) 2010 StatusNet, Inc.
 */

$(function() {
    /**
     * Find URL links from the source text that may be interesting.
     *
     * @param {String} text
     * @return {Array} list of URLs
     */
    function findLinks(text)
    {
        // @fixme match this to core code
        var re = /(?:^| )(https?:\/\/.+?\/.+?)(?= |$)/g;
        var links = [];
        var matches;
        while ((matches = re.exec(text)) !== null) {
            links.push(matches[1]);
        }
        return links;
    }

    /**
     * Start looking up info for a link preview...
     * May start async data loads.
     *
     * @param {String} id
     * @param {String} url
     */
    function prepLinkPreview(id, url)
    {
        console.log(id, url);
    }

    /**
     * Update the live preview section with links found in the given text.
     * May start async data loads.
     *
     * @param {String} text: free-form input text
     */
    function previewLinks(text)
    {
        var links = findLinks(text);
        for (var i = 0; i < links.length; i++) {
            var id = 'link-preview-' + i;
            prepLinkPreview(id, links[i]);
        }
    }

    $('#notice_data-text').change(function() {
       var text = $(this).val();
       previewLinks(text);
    });
});
